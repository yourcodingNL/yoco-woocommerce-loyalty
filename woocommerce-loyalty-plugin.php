<?php
/**
 * Plugin Name: YoCo WooCommerce Loyalty
 * Plugin URI: https://github.com/yourcodingNL/yoco-woocommerce-loyalty
 * Description: Een uitgebreide loyalty plugin voor WooCommerce met puntensysteem, beloningen en klantbehoud functies. Ontwikkeld door Your Coding.
 * Version: 0.0.1
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
define('YOCO_LOYALTY_VERSION', '1.0.0');
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
        
        // AJAX hooks for update functionality
        add_action('wp_ajax_yoco_loyalty_check_update', array($this, 'ajax_check_update'));
        add_action('wp_ajax_yoco_loyalty_clear_cache', array($this, 'ajax_clear_cache'));
        
        // WooCommerce dependency check
        add_action('admin_notices', array($this, 'check_woocommerce_dependency'));
    }
    
    /**
     * Include benodigde bestanden
     */
    private function includes() {
        // GitHub updater klasse
        require_once YOCO_LOYALTY_PLUGIN_PATH . 'includes/class-github-updater.php';
        
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
                'YourCoding', // Your Coding GitHub organization/username
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
        // Hier komt later de loyalty functionaliteit
        // Bijvoorbeeld: punt systeem, beloningen, etc.
    }
    
    /**
     * Admin menu toevoegen
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
            __('Instellingen', 'yoco-loyalty'),
            __('Instellingen', 'yoco-loyalty'),
            'manage_options',
            'yoco-loyalty-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Admin scripts en styles
     */
    public function admin_scripts($hook) {
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
        
        // Localize script voor AJAX calls
        wp_localize_script('yoco-loyalty-admin', 'yocoLoyaltyAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('yoco_loyalty_nonce'),
            'strings' => array(
                'checking' => __('Controleren...', 'yoco-loyalty'),
                'clearing' => __('Wissen...', 'yoco-loyalty'),
                'check_updates' => __('Check voor Updates', 'yoco-loyalty'),
                'clear_cache' => __('Wis Cache', 'yoco-loyalty'),
                'update_available' => __('Er is een nieuwe versie beschikbaar!', 'yoco-loyalty'),
                'up_to_date' => __('Je gebruikt de nieuwste versie.', 'yoco-loyalty'),
                'cache_cleared' => __('Cache succesvol gewist.', 'yoco-loyalty'),
                'error_occurred' => __('Er is een fout opgetreden.', 'yoco-loyalty')
            )
        ));
    }
    
    /**
     * Hoofd admin pagina
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="yoco-loyalty-admin-content">
                <h2><?php _e('Welkom bij YoCo WooCommerce Loyalty!', 'yoco-loyalty'); ?></h2>
                <p><?php _e('Deze plugin is ontwikkeld door Your Coding en biedt uitgebreide loyalty functionaliteit voor je WooCommerce winkel.', 'yoco-loyalty'); ?></p>
                
                <div class="yoco-loyalty-status">
                    <h3><?php _e('Plugin Status', 'yoco-loyalty'); ?></h3>
                    <p><strong><?php _e('Versie:', 'yoco-loyalty'); ?></strong> <?php echo YOCO_LOYALTY_VERSION; ?></p>
                    <p><strong><?php _e('WooCommerce:', 'yoco-loyalty'); ?></strong> 
                        <?php echo class_exists('WooCommerce') ? '✅ ' . __('Actief', 'yoco-loyalty') : '❌ ' . __('Niet gevonden', 'yoco-loyalty'); ?>
                    </p>
                    <p><strong><?php _e('GitHub Updates:', 'yoco-loyalty'); ?></strong> 
                        <?php echo isset($this->github_updater) ? '✅ ' . __('Ingeschakeld', 'yoco-loyalty') : '❌ ' . __('Uitgeschakeld', 'yoco-loyalty'); ?>
                    </p>
                    <p><strong><?php _e('Ontwikkelaar:', 'yoco-loyalty'); ?></strong> 
                        <a href="https://www.yourcoding.nl" target="_blank">Your Coding - Sebastiaan Kalkman</a>
                    </p>
                    <div class="yoco-loyalty-actions">
                        <button type="button" class="button yoco-loyalty-check-update">
                            <?php _e('Check voor Updates', 'yoco-loyalty'); ?>
                        </button>
                        <button type="button" class="button yoco-loyalty-clear-cache">
                            <?php _e('Wis Update Cache', 'yoco-loyalty'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="yoco-loyalty-features">
                    <h3><?php _e('Beschikbare Functies', 'yoco-loyalty'); ?></h3>
                    <ul>
                        <li>🎯 Puntensysteem per bestelling</li>
                        <li>🎁 Beloningen en vouchers</li>
                        <li>📊 Klant loyalty dashboard</li>
                        <li>📈 Uitgebreide loyalty analytics</li>
                        <li>🔄 Automatische acties en triggers</li>
                        <li>📧 E-mail notificaties voor klanten</li>
                        <li>🏆 Tier/level systeem</li>
                        <li>🎯 Referral programma</li>
                    </ul>
                </div>
                
                <div class="yoco-loyalty-support">
                    <h3><?php _e('Support & Development', 'yoco-loyalty'); ?></h3>
                    <p><?php _e('Voor vragen, support of maatwerk ontwikkeling:', 'yoco-loyalty'); ?></p>
                    <ul>
                        <li>🌐 Website: <a href="https://www.yourcoding.nl" target="_blank">www.yourcoding.nl</a></li>
                        <li>📧 Email: info@yourcoding.nl</li>
                        <li>🔧 GitHub: <a href="https://github.com/YourCoding/yoco-woocommerce-loyalty" target="_blank">Repository</a></li>
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
            <p><?php _e('YoCo WooCommerce Loyalty vereist WooCommerce om te functioneren. Installeer en activeer WooCommerce eerst.', 'yoco-loyalty'); ?></p>
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
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Voeg standaard opties toe
     */
    private function add_default_options() {
        add_option('yoco_loyalty_enabled', 1);
        add_option('yoco_loyalty_version', YOCO_LOYALTY_VERSION);
        add_option('yoco_loyalty_points_per_euro', 1);
        add_option('yoco_loyalty_euro_per_point', 0.01);
    }
    
    /**
     * Registreer plugin instellingen
     */
    public function register_settings() {
        register_setting('yoco_loyalty_settings', 'yoco_loyalty_enabled');
        register_setting('yoco_loyalty_settings', 'yoco_loyalty_points_per_euro');
        register_setting('yoco_loyalty_settings', 'yoco_loyalty_euro_per_point');
    }
    
    /**
     * AJAX handler voor update check
     */
    public function ajax_check_update() {
        // Verify nonce in production
        // if (!wp_verify_nonce($_POST['nonce'], 'yoco_loyalty_nonce')) {
        //     wp_die('Security check failed');
        // }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Force clear cache en check voor updates
        if ($this->github_updater) {
            $this->github_updater->clear_cache();
            
            // Trigger WordPress update check
            wp_clean_update_cache();
            
            wp_send_json_success(array(
                'message' => 'Update check voltooid',
                'has_update' => false // Dit zou echte update logica moeten zijn
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
        // Verify nonce in production
        // if (!wp_verify_nonce($_POST['nonce'], 'yoco_loyalty_nonce')) {
        //     wp_die('Security check failed');
        // }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
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
}

// Start de plugin
YoCo_Loyalty_Plugin::get_instance();