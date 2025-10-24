<?php
/**
 * Plugin Name: WooCommerce Loyalty Plugin
 * Plugin URI: https://github.com/jouwusername/woocommerce-loyalty-plugin
 * Description: Een uitgebreide loyalty plugin voor WooCommerce met puntensysteem, beloningen en klantbehoud functies.
 * Version: 1.0.0
 * Author: Jouw Naam
 * Author URI: https://jouwwebsite.nl
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-loyalty
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * Network: false
 * Update URI: https://github.com/jouwusername/woocommerce-loyalty-plugin
 * GitHub Plugin URI: jouwusername/woocommerce-loyalty-plugin
 * GitHub Branch: main
 */

// Voorkom directe toegang
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constanten definiëren
define('WC_LOYALTY_VERSION', '1.0.0');
define('WC_LOYALTY_PLUGIN_FILE', __FILE__);
define('WC_LOYALTY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WC_LOYALTY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_LOYALTY_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Hoofdklasse van de plugin
 */
class WC_Loyalty_Plugin {
    
    /**
     * Single instance van de plugin
     */
    private static $instance = null;
    
    /**
     * GitHub updater instance
     */
    private $github_updater;
    
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
        
        // WooCommerce dependency check
        add_action('admin_notices', array($this, 'check_woocommerce_dependency'));
    }
    
    /**
     * Include benodigde bestanden
     */
    private function includes() {
        // GitHub updater klasse
        require_once WC_LOYALTY_PLUGIN_PATH . 'includes/class-github-updater.php';
        
        // Admin klassen (worden later toegevoegd)
        if (is_admin()) {
            // require_once WC_LOYALTY_PLUGIN_PATH . 'includes/admin/class-admin.php';
        }
        
        // Frontend klassen (worden later toegevoegd)
        if (!is_admin()) {
            // require_once WC_LOYALTY_PLUGIN_PATH . 'includes/frontend/class-frontend.php';
        }
    }
    
    /**
     * Initialiseer GitHub updater
     */
    private function init_github_updater() {
        if (class_exists('WC_Loyalty_GitHub_Updater')) {
            $this->github_updater = new WC_Loyalty_GitHub_Updater(
                WC_LOYALTY_PLUGIN_FILE,
                'jouwusername', // Vervang met jouw GitHub username
                'woocommerce-loyalty-plugin', // Vervang met jouw repository naam
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
            wp_die(__('Deze plugin vereist WooCommerce om te functioneren.', 'wc-loyalty'));
        }
        
        // Maak database tabellen aan (later toe te voegen)
        $this->create_tables();
        
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
        load_plugin_textdomain('wc-loyalty', false, dirname(plugin_basename(__FILE__)) . '/languages/');
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
        // Hier komt later de loyalty functionaliteit
        // Bijvoorbeeld: punt systeem, beloningen, etc.
    }
    
    /**
     * Admin menu toevoegen
     */
    public function admin_menu() {
        add_menu_page(
            __('Loyalty Plugin', 'wc-loyalty'),
            __('Loyalty', 'wc-loyalty'),
            'manage_options',
            'wc-loyalty',
            array($this, 'admin_page'),
            'dashicons-heart',
            56
        );
        
        add_submenu_page(
            'wc-loyalty',
            __('Instellingen', 'wc-loyalty'),
            __('Instellingen', 'wc-loyalty'),
            'manage_options',
            'wc-loyalty-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Admin scripts en styles
     */
    public function admin_scripts($hook) {
        if (strpos($hook, 'wc-loyalty') === false) {
            return;
        }
        
        wp_enqueue_style(
            'wc-loyalty-admin',
            WC_LOYALTY_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WC_LOYALTY_VERSION
        );
        
        wp_enqueue_script(
            'wc-loyalty-admin',
            WC_LOYALTY_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WC_LOYALTY_VERSION,
            true
        );
    }
    
    /**
     * Hoofd admin pagina
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="wc-loyalty-admin-content">
                <h2><?php _e('Welkom bij de WooCommerce Loyalty Plugin!', 'wc-loyalty'); ?></h2>
                <p><?php _e('Deze plugin is in ontwikkeling. Binnenkort komen hier alle loyalty functies beschikbaar.', 'wc-loyalty'); ?></p>
                
                <div class="wc-loyalty-status">
                    <h3><?php _e('Plugin Status', 'wc-loyalty'); ?></h3>
                    <p><strong><?php _e('Versie:', 'wc-loyalty'); ?></strong> <?php echo WC_LOYALTY_VERSION; ?></p>
                    <p><strong><?php _e('WooCommerce:', 'wc-loyalty'); ?></strong> 
                        <?php echo class_exists('WooCommerce') ? '✅ ' . __('Actief', 'wc-loyalty') : '❌ ' . __('Niet gevonden', 'wc-loyalty'); ?>
                    </p>
                    <p><strong><?php _e('GitHub Updates:', 'wc-loyalty'); ?></strong> 
                        <?php echo isset($this->github_updater) ? '✅ ' . __('Ingeschakeld', 'wc-loyalty') : '❌ ' . __('Uitgeschakeld', 'wc-loyalty'); ?>
                    </p>
                </div>
                
                <div class="wc-loyalty-features">
                    <h3><?php _e('Geplande Functies', 'wc-loyalty'); ?></h3>
                    <ul>
                        <li>🎯 Puntensysteem</li>
                        <li>🎁 Beloningen en vouchers</li>
                        <li>📊 Klant dashboard</li>
                        <li>📈 Loyalty analytics</li>
                        <li>🔄 Automatische acties</li>
                        <li>📧 E-mail notificaties</li>
                    </ul>
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
            <h1><?php _e('Loyalty Plugin Instellingen', 'wc-loyalty'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('wc_loyalty_settings'); ?>
                <?php do_settings_sections('wc_loyalty_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Plugin Status', 'wc-loyalty'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wc_loyalty_enabled" value="1" <?php checked(get_option('wc_loyalty_enabled', 1)); ?> />
                                <?php _e('Loyalty functionaliteit inschakelen', 'wc-loyalty'); ?>
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
     * Check WooCommerce dependency
     */
    public function check_woocommerce_dependency() {
        if (!class_exists('WooCommerce')) {
            $this->woocommerce_missing_notice();
        }
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('WooCommerce Loyalty Plugin vereist WooCommerce om te functioneren. Installeer en activeer WooCommerce eerst.', 'wc-loyalty'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Maak database tabellen aan
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Punten tabel (wordt later uitgebreid)
        $table_name = $wpdb->prefix . 'wc_loyalty_points';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            points int(11) NOT NULL DEFAULT 0,
            total_earned int(11) NOT NULL DEFAULT 0,
            total_spent int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Voeg standaard opties toe
     */
    private function add_default_options() {
        add_option('wc_loyalty_enabled', 1);
        add_option('wc_loyalty_version', WC_LOYALTY_VERSION);
        add_option('wc_loyalty_points_per_euro', 1);
        add_option('wc_loyalty_euro_per_point', 0.01);
    }
}

// Start de plugin
WC_Loyalty_Plugin::get_instance();
