<?php
/**
 * Plugin Name: YoCo WooCommerce Loyalty
 * Plugin URI: https://github.com/yourcodingNL/yoco-woocommerce-loyalty
 * Description: Een uitgebreide loyalty plugin voor WooCommerce met puntensysteem, beloningen en klantbehoud functies. Ontwikkeld door Your Coding.
 * Version: 0.0.4
 * Author: Your Coding
 * Author URI: https://www.yourcoding.nl
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: yoco-loyalty
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * Network: false
 * Update URI: https://github.com/yourcodingNL/yoco-woocommerce-loyalty
 * GitHub Plugin URI: yourcodingNL/yoco-woocommerce-loyalty
 * GitHub Branch: main
 */

// Voorkom directe toegang
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constanten definiëren
define('YOCO_LOYALTY_VERSION', '0.0.4');
define('YOCO_LOYALTY_PLUGIN_FILE', __FILE__);
define('YOCO_LOYALTY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('YOCO_LOYALTY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('YOCO_LOYALTY_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Hoofdklasse van de plugin
 */
class YoCo_Loyalty_Plugin {
    
    /**
     * Single instance van de plugin
     */
    private static $instance = null;
    
    /**
     * GitHub updater instance
     */
    private $github_updater;
    
    /**
     * Points system instance
     */
    private $points_system;
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->includes();
        $this->init_github_updater();
    }
    
    /**
     * Singleton pattern - krijg de instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Hook initialisatie
     */
    private function init_hooks() {
        // Plugin activatie/deactivatie hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // WordPress init hooks
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'plugins_loaded'));
        
        // Admin hooks
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
        
        // AJAX hooks for update functionality
        add_action('wp_ajax_yoco_loyalty_check_update', array($this, 'ajax_check_update'));
        add_action('wp_ajax_yoco_loyalty_clear_cache', array($this, 'ajax_clear_cache'));
        
        // WooCommerce dependency check
        add_action('admin_notices', array($this, 'check_woocommerce_dependency'));
        
        // Database version check
        add_action('plugins_loaded', array($this, 'check_database_version'));
    }
    
    /**
     * Include benodigde bestanden
     */
    private function includes() {
        // GitHub updater klasse
        require_once YOCO_LOYALTY_PLUGIN_PATH . 'includes/class-github-updater.php';
        
        // Database klasse
        require_once YOCO_LOYALTY_PLUGIN_PATH . 'includes/class-database.php';
        
        // Points system klasse
        require_once YOCO_LOYALTY_PLUGIN_PATH . 'includes/class-points-system.php';
        
        // Admin klassen (worden later toegevoegd)
        if (is_admin()) {
            // require_once YOCO_LOYALTY_PLUGIN_PATH . 'includes/admin/class-admin.php';
        }
        
        // Frontend klassen (worden later toegevoegd)
        if (!is_admin()) {
            // require_once YOCO_LOYALTY_PLUGIN_PATH . 'includes/frontend/class-frontend.php';
        }
    }
    
    /**
     * Initialiseer GitHub updater
     */
    private function init_github_updater() {
        if (class_exists('YoCo_Loyalty_GitHub_Updater')) {
            $this->github_updater = new YoCo_Loyalty_GitHub_Updater(
                YOCO_LOYALTY_PLUGIN_FILE,
                'yourcodingNL', // GitHub username
                'yoco-woocommerce-loyalty', // Repository naam
                'main' // Branch naam
            );
        }
    }
    
    /**
     * Plugin activatie
     */
    public function activate() {
        // Check WooCommerce dependency
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('YoCo Loyalty Plugin vereist WooCommerce om te functioneren.', 'yoco-loyalty'));
        }
        
        // Maak database tabellen aan
        YoCo_Loyalty_Database::create_tables();
        
        // Voeg default opties toe
        $this->add_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivatie
     */
    public function deactivate() {
        // Cleanup taken
        flush_rewrite_rules();
    }
    
    /**
     * Plugin initialisatie
     */
    public function init() {
        // Laad tekstdomein voor vertalingen
        load_plugin_textdomain('yoco-loyalty', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Na het laden van alle plugins
     */
    public function plugins_loaded() {
        // Check of WooCommerce actief is
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Initialiseer plugin functionaliteit
        $this->init_plugin();
    }
    
    /**
     * Initialiseer plugin functionaliteit
     */
    private function init_plugin() {
        // Initialiseer points system
        if (class_exists('YoCo_Loyalty_Points_System')) {
            $this->points_system = new YoCo_Loyalty_Points_System();
        }
    }
    
    /**
     * Check database versie en update indien nodig
     */
    public function check_database_version() {
        YoCo_Loyalty_Database::check_database_version();
    }
    
    /**
     * WooCommerce ontbrekende notice
     */
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo __('YoCo Loyalty Plugin vereist WooCommerce om te functioneren. Installeer en activeer WooCommerce eerst.', 'yoco-loyalty');
        echo '</p></div>';
    }
    
    /**
     * Check WooCommerce dependency
     */
    public function check_woocommerce_dependency() {
        if (!class_exists('WooCommerce') && current_user_can('manage_options')) {
            echo '<div class="notice notice-warning"><p>';
            echo __('YoCo Loyalty Plugin is geactiveerd maar WooCommerce is niet gevonden. Sommige functies werken mogelijk niet correct.', 'yoco-loyalty');
            echo '</p></div>';
        }
    }
    
    /**
     * Admin menu
     */
    public function admin_menu() {
        add_menu_page(
            __('YoCo Loyalty', 'yoco-loyalty'),
            __('YoCo Loyalty', 'yoco-loyalty'),
            'manage_options',
            'yoco-loyalty',
            array($this, 'admin_page'),
            'dashicons-heart',
            56
        );
        
        add_submenu_page(
            'yoco-loyalty',
            __('Dashboard', 'yoco-loyalty'),
            __('Dashboard', 'yoco-loyalty'),
            'manage_options',
            'yoco-loyalty',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'yoco-loyalty',
            __('Instellingen', 'yoco-loyalty'),
            __('Instellingen', 'yoco-loyalty'),
            'manage_options',
            'yoco-loyalty-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'yoco-loyalty',
            __('Punten Beheer', 'yoco-loyalty'),
            __('Punten Beheer', 'yoco-loyalty'),
            'manage_options',
            'yoco-loyalty-points',
            array($this, 'points_page')
        );
    }
    
    /**
     * Admin scripts en styles
     */
    public function admin_scripts($hook) {
        // Alleen laden op onze admin pagina's
        if (strpos($hook, 'yoco-loyalty') === false) {
            return;
        }
        
        wp_enqueue_style(
            'yoco-loyalty-admin',
            YOCO_LOYALTY_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            YOCO_LOYALTY_VERSION
        );
        
        wp_enqueue_script(
            'yoco-loyalty-admin',
            YOCO_LOYALTY_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            YOCO_LOYALTY_VERSION,
            true
        );
        
        wp_localize_script('yoco-loyalty-admin', 'yocoLoyalty', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('yoco_loyalty_nonce')
        ));
    }
    
    /**
     * Frontend scripts en styles
     */
    public function frontend_scripts() {
        if (!get_option('yoco_loyalty_enabled', 1)) {
            return;
        }
        
        wp_enqueue_style(
            'yoco-loyalty-frontend',
            YOCO_LOYALTY_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            YOCO_LOYALTY_VERSION
        );
    }
    
    /**
     * Voeg standaard opties toe
     */
    private function add_default_options() {
        add_option('yoco_loyalty_enabled', 1);
        add_option('yoco_loyalty_version', YOCO_LOYALTY_VERSION);
        add_option('yoco_loyalty_points_per_euro', 1);
        add_option('yoco_loyalty_euro_per_point', 0.01);
        add_option('yoco_loyalty_min_points_redeem', 100);
        add_option('yoco_loyalty_show_in_account', 1);
        add_option('yoco_loyalty_show_on_thankyou', 1);
    }
    
    /**
     * Registreer plugin instellingen
     */
    public function register_settings() {
        register_setting('yoco_loyalty_settings', 'yoco_loyalty_enabled');
        register_setting('yoco_loyalty_settings', 'yoco_loyalty_points_per_euro');
        register_setting('yoco_loyalty_settings', 'yoco_loyalty_euro_per_point');
        register_setting('yoco_loyalty_settings', 'yoco_loyalty_min_points_redeem');
        register_setting('yoco_loyalty_settings', 'yoco_loyalty_show_in_account');
        register_setting('yoco_loyalty_settings', 'yoco_loyalty_show_on_thankyou');
    }
    
    /**
     * Hoofd admin pagina - Dashboard
     */
    public function admin_page() {
        $stats = YoCo_Loyalty_Database::get_stats();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="yoco-loyalty-admin-content">
                <h2><?php _e('YoCo Loyalty Dashboard', 'yoco-loyalty'); ?></h2>
                
                <div class="yoco-loyalty-stats-grid">
                    <div class="yoco-loyalty-stat-card">
                        <h3><?php _e('Totaal Gebruikers', 'yoco-loyalty'); ?></h3>
                        <div class="stat-number"><?php echo number_format($stats['total_users'] ?? 0); ?></div>
                        <p><?php _e('gebruikers met punten', 'yoco-loyalty'); ?></p>
                    </div>
                    
                    <div class="yoco-loyalty-stat-card">
                        <h3><?php _e('Punten in Omloop', 'yoco-loyalty'); ?></h3>
                        <div class="stat-number"><?php echo number_format($stats['total_points'] ?? 0); ?></div>
                        <p><?php _e('totaal actieve punten', 'yoco-loyalty'); ?></p>
                    </div>
                    
                    <div class="yoco-loyalty-stat-card">
                        <h3><?php _e('Totaal Verdiend', 'yoco-loyalty'); ?></h3>
                        <div class="stat-number"><?php echo number_format($stats['total_earned'] ?? 0); ?></div>
                        <p><?php _e('punten ooit verdiend', 'yoco-loyalty'); ?></p>
                    </div>
                    
                    <div class="yoco-loyalty-stat-card">
                        <h3><?php _e('Gemiddeld per Gebruiker', 'yoco-loyalty'); ?></h3>
                        <div class="stat-number"><?php echo number_format($stats['avg_points_per_user'] ?? 0, 1); ?></div>
                        <p><?php _e('punten per gebruiker', 'yoco-loyalty'); ?></p>
                    </div>
                </div>
                
                <div class="yoco-loyalty-status">
                    <h3><?php _e('Plugin Status', 'yoco-loyalty'); ?></h3>
                    <p><strong><?php _e('Versie:', 'yoco-loyalty'); ?></strong> <?php echo YOCO_LOYALTY_VERSION; ?></p>
                    <p><strong><?php _e('Status:', 'yoco-loyalty'); ?></strong> 
                        <?php echo get_option('yoco_loyalty_enabled', 1) ? '✅ ' . __('Actief', 'yoco-loyalty') : '❌ ' . __('Uitgeschakeld', 'yoco-loyalty'); ?>
                    </p>
                    <p><strong><?php _e('WooCommerce:', 'yoco-loyalty'); ?></strong> 
                        <?php echo class_exists('WooCommerce') ? '✅ ' . __('Actief', 'yoco-loyalty') : '❌ ' . __('Niet gevonden', 'yoco-loyalty'); ?>
                    </p>
                    <p><strong><?php _e('Ontwikkelaar:', 'yoco-loyalty'); ?></strong> 
                        <a href="https://www.yourcoding.nl" target="_blank">Your Coding - Sebastiaan Kalkman</a>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Instellingen pagina
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('YoCo Loyalty Instellingen', 'yoco-loyalty'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('yoco_loyalty_settings'); ?>
                <?php do_settings_sections('yoco_loyalty_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Plugin Status', 'yoco-loyalty'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="yoco_loyalty_enabled" value="1" <?php checked(get_option('yoco_loyalty_enabled', 1)); ?> />
                                <?php _e('YoCo Loyalty functionaliteit inschakelen', 'yoco-loyalty'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Punten per Euro', 'yoco-loyalty'); ?></th>
                        <td>
                            <input type="number" name="yoco_loyalty_points_per_euro" value="<?php echo get_option('yoco_loyalty_points_per_euro', 1); ?>" min="0" step="0.1" />
                            <p class="description"><?php _e('Hoeveel punten krijgt een klant per uitgegeven euro?', 'yoco-loyalty'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Euro per Punt', 'yoco-loyalty'); ?></th>
                        <td>
                            <input type="number" name="yoco_loyalty_euro_per_point" value="<?php echo get_option('yoco_loyalty_euro_per_point', 0.01); ?>" min="0" step="0.01" />
                            <p class="description"><?php _e('Hoeveel euro is 1 punt waard bij inwisseling?', 'yoco-loyalty'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Minimum Punten voor Inwisseling', 'yoco-loyalty'); ?></th>
                        <td>
                            <input type="number" name="yoco_loyalty_min_points_redeem" value="<?php echo get_option('yoco_loyalty_min_points_redeem', 100); ?>" min="1" />
                            <p class="description"><?php _e('Minimaal aantal punten voordat klanten kunnen inwisselen.', 'yoco-loyalty'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Weergave Opties', 'yoco-loyalty'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="yoco_loyalty_show_in_account" value="1" <?php checked(get_option('yoco_loyalty_show_in_account', 1)); ?> />
                                <?php _e('Toon punten in My Account dashboard', 'yoco-loyalty'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="yoco_loyalty_show_on_thankyou" value="1" <?php checked(get_option('yoco_loyalty_show_on_thankyou', 1)); ?> />
                                <?php _e('Toon verdiende punten op bedankt pagina', 'yoco-loyalty'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Punten beheer pagina
     */
    public function points_page() {
        global $wpdb;
        
        // Handle manual point adjustments
        if (isset($_POST['adjust_points']) && wp_verify_nonce($_POST['_wpnonce'], 'yoco_loyalty_adjust_points')) {
            $user_id = intval($_POST['user_id']);
            $points = intval($_POST['points']);
            $action = sanitize_text_field($_POST['action']);
            
            if ($user_id && $points && $this->points_system) {
                if ($action === 'add') {
                    $this->points_system->add_points_to_user($user_id, $points, 'admin');
                    echo '<div class="notice notice-success"><p>' . sprintf(__('%d punten toegevoegd aan gebruiker.', 'yoco-loyalty'), $points) . '</p></div>';
                } elseif ($action === 'remove') {
                    $this->points_system->remove_points_from_user($user_id, $points, 'admin');
                    echo '<div class="notice notice-success"><p>' . sprintf(__('%d punten afgetrokken van gebruiker.', 'yoco-loyalty'), $points) . '</p></div>';
                }
            }
        }
        
        // Get users with points
        $points_table = $wpdb->prefix . 'yoco_loyalty_points';
        $users_with_points = $wpdb->get_results("
            SELECT p.*, u.display_name, u.user_email 
            FROM $points_table p 
            LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID 
            ORDER BY p.points DESC 
            LIMIT 50
        ");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Punten Beheer', 'yoco-loyalty'); ?></h1>
            
            <div class="yoco-loyalty-points-admin">
                <h2><?php _e('Handmatige Punten Aanpassing', 'yoco-loyalty'); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('yoco_loyalty_adjust_points'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="user_id"><?php _e('Gebruiker ID', 'yoco-loyalty'); ?></label></th>
                            <td><input type="number" id="user_id" name="user_id" min="1" required /></td>
                        </tr>
                        <tr>
                            <th><label for="points"><?php _e('Punten', 'yoco-loyalty'); ?></label></th>
                            <td><input type="number" id="points" name="points" min="1" required /></td>
                        </tr>
                        <tr>
                            <th><label for="action"><?php _e('Actie', 'yoco-loyalty'); ?></label></th>
                            <td>
                                <select id="action" name="action" required>
                                    <option value="add"><?php _e('Toevoegen', 'yoco-loyalty'); ?></option>
                                    <option value="remove"><?php _e('Aftrekken', 'yoco-loyalty'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="adjust_points" class="button-primary" value="<?php _e('Punten Aanpassen', 'yoco-loyalty'); ?>" />
                    </p>
                </form>
                
                <h2><?php _e('Gebruikers met Punten', 'yoco-loyalty'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Gebruiker', 'yoco-loyalty'); ?></th>
                            <th><?php _e('Email', 'yoco-loyalty'); ?></th>
                            <th><?php _e('Huidige Punten', 'yoco-loyalty'); ?></th>
                            <th><?php _e('Totaal Verdiend', 'yoco-loyalty'); ?></th>
                            <th><?php _e('Totaal Besteed', 'yoco-loyalty'); ?></th>
                            <th><?php _e('Laatst Bijgewerkt', 'yoco-loyalty'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users_with_points): ?>
                            <?php foreach ($users_with_points as $user): ?>
                                <tr>
                                    <td><?php echo esc_html($user->display_name ?: 'ID: ' . $user->user_id); ?></td>
                                    <td><?php echo esc_html($user->user_email); ?></td>
                                    <td><strong><?php echo number_format($user->points); ?></strong></td>
                                    <td><?php echo number_format($user->total_earned); ?></td>
                                    <td><?php echo number_format($user->total_spent); ?></td>
                                    <td><?php echo date('d-m-Y H:i', strtotime($user->updated_at)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6"><?php _e('Nog geen gebruikers met punten gevonden.', 'yoco-loyalty'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler voor update check
     */
    public function ajax_check_update() {
        if (!wp_verify_nonce($_POST['nonce'], 'yoco_loyalty_nonce') || !current_user_can('manage_options')) {
            wp_die('Security check failed');
        }
        
        // Force clear cache en check voor updates
        if ($this->github_updater) {
            $this->github_updater->clear_cache();
            wp_clean_update_cache();
            
            wp_send_json_success(array(
                'message' => 'Update check voltooid'
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'GitHub updater niet beschikbaar'
            ));
        }
    }
    
    /**
     * AJAX handler voor cache wissen
     */
    public function ajax_clear_cache() {
        if (!wp_verify_nonce($_POST['nonce'], 'yoco_loyalty_nonce') || !current_user_can('manage_options')) {
            wp_die('Security check failed');
        }
        
        if ($this->github_updater) {
            $this->github_updater->clear_cache();
            wp_clean_update_cache();
            
            wp_send_json_success(array(
                'message' => 'Cache succesvol gewist'
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'GitHub updater niet beschikbaar'
            ));
        }
    }
    
    /**
     * Get points system instance
     */
    public function get_points_system() {
        return $this->points_system;
    }
}

// Start de plugin
YoCo_Loyalty_Plugin::get_instance();

// Global helper functie voor andere plugins/themes
function yoco_loyalty_get_user_points($user_id) {
    $plugin = YoCo_Loyalty_Plugin::get_instance();
    $points_system = $plugin->get_points_system();
    if ($points_system) {
        return $points_system->get_user_points($user_id);
    }
    return 0;
}