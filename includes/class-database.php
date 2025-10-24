<?php
/**
 * YoCo Loyalty Database Setup
 * 
 * Handles database table creation and updates
 */

// Voorkom directe toegang
if (!defined('ABSPATH')) {
    exit;
}

class YoCo_Loyalty_Database {
    
    /**
     * Maak alle benodigde database tabellen aan
     */
    public static function create_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Set charset
        $charset_collate = $wpdb->get_charset_collate();
        
        // Punten tabel
        self::create_points_table($charset_collate);
        
        // Transacties tabel
        self::create_transactions_table($charset_collate);
        
        // Update versie
        update_option('yoco_loyalty_db_version', YOCO_LOYALTY_VERSION);
    }
    
    /**
     * Maak punten tabel aan
     */
    private static function create_points_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'yoco_loyalty_points';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            points int(11) NOT NULL DEFAULT 0,
            total_earned int(11) NOT NULL DEFAULT 0,
            total_spent int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY points (points),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Maak transacties tabel aan
     */
    private static function create_transactions_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'yoco_loyalty_transactions';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            points int(11) NOT NULL,
            type varchar(50) NOT NULL DEFAULT 'manual',
            action varchar(20) NOT NULL DEFAULT 'earned',
            reference_id bigint(20) UNSIGNED DEFAULT NULL,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY action (action),
            KEY reference_id (reference_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Check en update database indien nodig
     */
    public static function check_database_version() {
        $installed_version = get_option('yoco_loyalty_db_version');
        
        if ($installed_version !== YOCO_LOYALTY_VERSION) {
            self::create_tables();
        }
    }
    
    /**
     * Drop alle tabellen (voor deinstallatie)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'yoco_loyalty_points',
            $wpdb->prefix . 'yoco_loyalty_transactions'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('yoco_loyalty_db_version');
    }
    
    /**
     * Krijg database statistieken
     */
    public static function get_stats() {
        global $wpdb;
        
        $points_table = $wpdb->prefix . 'yoco_loyalty_points';
        $transactions_table = $wpdb->prefix . 'yoco_loyalty_transactions';
        
        $stats = array();
        
        // Total users with points
        $stats['total_users'] = $wpdb->get_var("SELECT COUNT(*) FROM $points_table WHERE points > 0");
        
        // Total points in circulation
        $stats['total_points'] = $wpdb->get_var("SELECT SUM(points) FROM $points_table");
        
        // Total points earned ever
        $stats['total_earned'] = $wpdb->get_var("SELECT SUM(total_earned) FROM $points_table");
        
        // Total points spent ever
        $stats['total_spent'] = $wpdb->get_var("SELECT SUM(total_spent) FROM $points_table");
        
        // Total transactions
        $stats['total_transactions'] = $wpdb->get_var("SELECT COUNT(*) FROM $transactions_table");
        
        // Average points per user
        if ($stats['total_users'] > 0) {
            $stats['avg_points_per_user'] = round($stats['total_points'] / $stats['total_users'], 2);
        } else {
            $stats['avg_points_per_user'] = 0;
        }
        
        return $stats;
    }
    
    /**
     * Cleanup oude transacties (optioneel, voor performance)
     */
    public static function cleanup_old_transactions($days = 365) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'yoco_loyalty_transactions';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < %s",
            $cutoff_date
        ));
        
        return $deleted;
    }
}