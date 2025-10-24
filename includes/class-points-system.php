<?php
/**
 * YoCo Loyalty Points System
 * 
 * Handles all points-related functionality
 */

// Voorkom directe toegang
if (!defined('ABSPATH')) {
    exit;
}

class YoCo_Loyalty_Points_System {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialiseer WordPress hooks
     */
    private function init_hooks() {
        // Ken punten toe wanneer bestelling wordt voltooid
        add_action('woocommerce_order_status_completed', array($this, 'award_points_for_order'));
        
        // Ken punten toe wanneer status van processing naar completed gaat
        add_action('woocommerce_order_status_processing_to_completed', array($this, 'award_points_for_order'));
        
        // Verwijder punten als bestelling wordt geannuleerd/gerefund
        add_action('woocommerce_order_status_completed_to_cancelled', array($this, 'remove_points_for_order'));
        add_action('woocommerce_order_status_completed_to_refunded', array($this, 'remove_points_for_order'));
        
        // Toon punten info op thank you page
        add_action('woocommerce_thankyou', array($this, 'show_points_on_thankyou_page'));
        
        // Toon punten saldo in my account
        add_action('woocommerce_account_dashboard', array($this, 'show_points_in_account_dashboard'));
    }
    
    /**
     * Ken punten toe voor een bestelling
     * 
     * @param int $order_id
     */
    public function award_points_for_order($order_id) {
        // Controleer of plugin actief is
        if (!get_option('yoco_loyalty_enabled', 1)) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Controleer of punten al zijn toegekend
        if ($order->get_meta('_yoco_loyalty_points_awarded')) {
            return;
        }
        
        $user_id = $order->get_user_id();
        if (!$user_id) {
            return; // Alleen voor geregistreerde gebruikers
        }
        
        // Bereken punten gebaseerd op besteltotaal (exclusief verzendkosten en belasting)
        $order_total = $order->get_subtotal(); // Subtotaal zonder verzending/belasting
        $points_per_euro = get_option('yoco_loyalty_points_per_euro', 1);
        $points_to_award = floor($order_total * $points_per_euro);
        
        if ($points_to_award > 0) {
            // Ken punten toe
            $this->add_points_to_user($user_id, $points_to_award, 'order', $order_id);
            
            // Markeer als toegekend
            $order->update_meta_data('_yoco_loyalty_points_awarded', $points_to_award);
            $order->update_meta_data('_yoco_loyalty_points_date', current_time('mysql'));
            $order->save();
            
            // Voeg order note toe
            $order->add_order_note(
                sprintf(
                    __('%d loyalty punten toegekend aan klant.', 'yoco-loyalty'),
                    $points_to_award
                )
            );
        }
    }
    
    /**
     * Verwijder punten voor een geannuleerde/gerefunde bestelling
     * 
     * @param int $order_id
     */
    public function remove_points_for_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $points_awarded = $order->get_meta('_yoco_loyalty_points_awarded');
        if (!$points_awarded) {
            return;
        }
        
        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }
        
        // Trek punten af
        $this->remove_points_from_user($user_id, $points_awarded, 'order_cancelled', $order_id);
        
        // Verwijder meta data
        $order->delete_meta_data('_yoco_loyalty_points_awarded');
        $order->delete_meta_data('_yoco_loyalty_points_date');
        $order->save();
        
        // Voeg order note toe
        $order->add_order_note(
            sprintf(
                __('%d loyalty punten teruggetrokken vanwege annulering/refund.', 'yoco-loyalty'),
                $points_awarded
            )
        );
    }
    
    /**
     * Voeg punten toe aan gebruiker
     * 
     * @param int $user_id
     * @param int $points
     * @param string $type (order, bonus, admin, etc.)
     * @param int $reference_id (order_id, etc.)
     */
    public function add_points_to_user($user_id, $points, $type = 'manual', $reference_id = 0) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'yoco_loyalty_points';
        
        // Krijg huidige punten
        $current_points = $this->get_user_points($user_id);
        
        // Update of insert punten record
        $wpdb->replace(
            $table_name,
            array(
                'user_id' => $user_id,
                'points' => $current_points + $points,
                'total_earned' => $this->get_user_total_earned($user_id) + $points,
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s')
        );
        
        // Log de transactie
        $this->log_points_transaction($user_id, $points, $type, $reference_id, 'earned');
        
        return true;
    }
    
    /**
     * Trek punten af van gebruiker
     * 
     * @param int $user_id
     * @param int $points
     * @param string $type
     * @param int $reference_id
     */
    public function remove_points_from_user($user_id, $points, $type = 'manual', $reference_id = 0) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'yoco_loyalty_points';
        
        // Krijg huidige punten
        $current_points = $this->get_user_points($user_id);
        
        // Voorkom negatieve punten
        $new_points = max(0, $current_points - $points);
        
        // Update punten record
        $wpdb->replace(
            $table_name,
            array(
                'user_id' => $user_id,
                'points' => $new_points,
                'total_spent' => $this->get_user_total_spent($user_id) + $points,
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s')
        );
        
        // Log de transactie
        $this->log_points_transaction($user_id, $points, $type, $reference_id, 'spent');
        
        return true;
    }
    
    /**
     * Krijg punten saldo van gebruiker
     * 
     * @param int $user_id
     * @return int
     */
    public function get_user_points($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'yoco_loyalty_points';
        
        $points = $wpdb->get_var($wpdb->prepare(
            "SELECT points FROM {$table_name} WHERE user_id = %d",
            $user_id
        ));
        
        return $points ? (int) $points : 0;
    }
    
    /**
     * Krijg totaal verdiende punten van gebruiker
     * 
     * @param int $user_id
     * @return int
     */
    public function get_user_total_earned($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'yoco_loyalty_points';
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT total_earned FROM {$table_name} WHERE user_id = %d",
            $user_id
        ));
        
        return $total ? (int) $total : 0;
    }
    
    /**
     * Krijg totaal bestede punten van gebruiker
     * 
     * @param int $user_id
     * @return int
     */
    public function get_user_total_spent($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'yoco_loyalty_points';
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT total_spent FROM {$table_name} WHERE user_id = %d",
            $user_id
        ));
        
        return $total ? (int) $total : 0;
    }
    
    /**
     * Log een punten transactie
     * 
     * @param int $user_id
     * @param int $points
     * @param string $type
     * @param int $reference_id
     * @param string $action (earned, spent, expired)
     */
    private function log_points_transaction($user_id, $points, $type, $reference_id, $action) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'yoco_loyalty_transactions';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'points' => $points,
                'type' => $type,
                'action' => $action,
                'reference_id' => $reference_id,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%d', '%s')
        );
    }
    
    /**
     * Toon punten info op bedankt pagina
     * 
     * @param int $order_id
     */
    public function show_points_on_thankyou_page($order_id) {
        if (!get_option('yoco_loyalty_enabled', 1)) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order || !$order->get_user_id()) {
            return;
        }
        
        $points_awarded = $order->get_meta('_yoco_loyalty_points_awarded');
        if ($points_awarded) {
            $current_points = $this->get_user_points($order->get_user_id());
            
            echo '<div class="yoco-loyalty-thankyou">';
            echo '<h3>' . __('Loyalty Punten', 'yoco-loyalty') . '</h3>';
            echo '<p>' . sprintf(
                __('Je hebt %d punten verdiend met deze bestelling! Je hebt nu in totaal %d punten.', 'yoco-loyalty'),
                $points_awarded,
                $current_points
            ) . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Toon punten saldo in my account dashboard
     */
    public function show_points_in_account_dashboard() {
        if (!get_option('yoco_loyalty_enabled', 1)) {
            return;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }
        
        $current_points = $this->get_user_points($user_id);
        $total_earned = $this->get_user_total_earned($user_id);
        $euro_value = $current_points * get_option('yoco_loyalty_euro_per_point', 0.01);
        
        echo '<div class="yoco-loyalty-dashboard">';
        echo '<h3>' . __('Jouw Loyalty Punten', 'yoco-loyalty') . '</h3>';
        echo '<div class="yoco-loyalty-stats">';
        echo '<div class="yoco-loyalty-stat">';
        echo '<span class="label">' . __('Huidige Saldo:', 'yoco-loyalty') . '</span>';
        echo '<span class="value">' . number_format($current_points) . ' punten</span>';
        echo '</div>';
        echo '<div class="yoco-loyalty-stat">';
        echo '<span class="label">' . __('Waarde:', 'yoco-loyalty') . '</span>';
        echo '<span class="value">€' . number_format($euro_value, 2) . '</span>';
        echo '</div>';
        echo '<div class="yoco-loyalty-stat">';
        echo '<span class="label">' . __('Totaal Verdiend:', 'yoco-loyalty') . '</span>';
        echo '<span class="value">' . number_format($total_earned) . ' punten</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Bereken punten voor een bedrag
     * 
     * @param float $amount
     * @return int
     */
    public function calculate_points_for_amount($amount) {
        $points_per_euro = get_option('yoco_loyalty_points_per_euro', 1);
        return floor($amount * $points_per_euro);
    }
    
    /**
     * Bereken waarde van punten in euro
     * 
     * @param int $points
     * @return float
     */
    public function calculate_points_value($points) {
        $euro_per_point = get_option('yoco_loyalty_euro_per_point', 0.01);
        return $points * $euro_per_point;
    }
}