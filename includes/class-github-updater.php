<?php
/**
 * GitHub Plugin Updater
 * 
 * Zorgt voor automatische plugin updates vanuit een GitHub repository
 * Versie: Gefixed voor mapnaam en update detectie problemen
 */

// Voorkom directe toegang
if (!defined('ABSPATH')) {
    exit;
}

class YoCo_Loyalty_GitHub_Updater {
    
    /**
     * Plugin bestand pad
     */
    private $plugin_file;
    
    /**
     * Plugin data
     */
    private $plugin_data;
    
    /**
     * Plugin basename
     */
    private $plugin_basename;
    
    /**
     * Plugin slug
     */
    private $plugin_slug;
    
    /**
     * GitHub gebruikersnaam
     */
    private $github_username;
    
    /**
     * GitHub repository naam
     */
    private $github_repo;
    
    /**
     * GitHub branch
     */
    private $github_branch;
    
    /**
     * GitHub API token (optioneel voor private repos)
     */
    private $github_token;
    
    /**
     * Constructor
     */
    public function __construct($plugin_file, $github_username, $github_repo, $github_branch = 'main', $github_token = null) {
        $this->plugin_file = $plugin_file;
        $this->plugin_basename = plugin_basename($this->plugin_file);
        $this->plugin_slug = dirname($this->plugin_basename);
        $this->github_username = $github_username;
        $this->github_repo = $github_repo;
        $this->github_branch = $github_branch;
        $this->github_token = $github_token;
        
        // Krijg plugin data
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $this->plugin_data = get_plugin_data($this->plugin_file);
        
        // Initialiseer hooks
        $this->init_hooks();
    }
    
    /**
     * Initialiseer WordPress hooks
     */
    private function init_hooks() {
        // Pre-set transient filter
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        
        // Plugin API call filter
        add_filter('plugins_api', array($this, 'plugin_api_call'), 10, 3);
        
        // Plugin upgrade filter
        add_filter('upgrader_pre_download', array($this, 'download_package'), 10, 3);
        
        // Plugin row meta
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
        
        // FIX: Hook voor het hernoemen van de plugin folder
        add_filter('upgrader_source_selection', array($this, 'fix_plugin_folder_name'), 10, 4);
    }
    
    /**
     * Check voor updates
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // Krijg remote versie info
        $remote_version = $this->get_remote_version();
        
        if ($remote_version === false) {
            return $transient;
        }
        
        // Vergelijk versies
        if (version_compare($this->plugin_data['Version'], $remote_version['version'], '<')) {
            $transient->response[$this->plugin_basename] = (object) array(
                'slug' => $this->plugin_slug,
                'plugin' => $this->plugin_basename,
                'new_version' => $remote_version['version'],
                'tested' => $remote_version['tested'],
                'package' => $remote_version['download_url'],
                'url' => $this->plugin_data['PluginURI']
            );
        }
        
        return $transient;
    }
    
    /**
     * Plugin API call
     */
    public function plugin_api_call($result, $action, $args) {
        if ($action !== 'plugin_information' || $args->slug !== $this->plugin_slug) {
            return $result;
        }
        
        $remote_version = $this->get_remote_version();
        
        if ($remote_version === false) {
            return $result;
        }
        
        return (object) array(
            'slug' => $this->plugin_slug,
            'plugin_name' => $this->plugin_data['Name'],
            'name' => $this->plugin_data['Name'],
            'version' => $remote_version['version'],
            'author' => $this->plugin_data['AuthorName'],
            'author_profile' => $this->plugin_data['AuthorURI'],
            'last_updated' => $remote_version['last_updated'],
            'homepage' => $this->plugin_data['PluginURI'],
            'short_description' => $this->plugin_data['Description'],
            'sections' => array(
                'description' => $this->plugin_data['Description'],
                'changelog' => 'Laatste versie: ' . $remote_version['version']
            ),
            'download_link' => $remote_version['download_url'],
            'tested' => $remote_version['tested'],
            'requires' => $remote_version['requires'],
            'requires_php' => $remote_version['requires_php'],
        );
    }
    
    /**
     * Download package
     */
    public function download_package($source, $upgrader, $hook_extra) {
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
            return $source;
        }
        
        $remote_version = $this->get_remote_version();
        
        if ($remote_version === false) {
            return new WP_Error('no_package', __('Update package niet beschikbaar', 'yoco-loyalty'));
        }
        
        $package = $remote_version['download_url'];
        
        // Voeg GitHub token toe als beschikbaar
        $args = array();
        if ($this->github_token) {
            $args['headers'] = array(
                'Authorization' => 'token ' . $this->github_token,
            );
        }
        
        $response = wp_remote_get($package, $args);
        
        if (is_wp_error($response)) {
            return new WP_Error('download_failed', $response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if (200 !== $code) {
            return new WP_Error('download_failed', sprintf(__('Download failed met HTTP code %d', 'yoco-loyalty'), $code));
        }
        
        $temp_file = download_url($package, 300, false, $args);
        
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }
        
        return $temp_file;
    }
    
    /**
     * FIX: Hernoem plugin folder naar correcte naam
     */
    public function fix_plugin_folder_name($source, $remote_source, $upgrader, $hook_extra = null) {
        // Check of dit onze plugin is
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
            return $source;
        }
        
        // Gewenste folder naam (moet exact zijn: yoco-woocommerce-loyalty)
        $desired_name = 'yoco-woocommerce-loyalty';
        $corrected_source = trailingslashit($remote_source) . $desired_name . '/';
        
        // Als de source folder naam niet correct is, hernoem deze
        if (basename(untrailingslashit($source)) !== $desired_name) {
            if (move($source, $corrected_source)) {
                return $corrected_source;
            }
        }
        
        return $source;
    }
    
    /**
     * Plugin row meta
     */
    public function plugin_row_meta($links, $file) {
        if ($file === $this->plugin_basename) {
            $links[] = sprintf(
                '<a href="%s" target="_blank">%s</a>',
                esc_url($this->plugin_data['PluginURI']),
                __('GitHub Repository', 'yoco-loyalty')
            );
            
            // Check voor update knop
            $links[] = sprintf(
                '<a href="#" onclick="yocoLoyaltyCheckUpdate(); return false;">%s</a>',
                __('Check voor Updates', 'yoco-loyalty')
            );
            
            // Cache wissen knop
            $links[] = sprintf(
                '<a href="#" onclick="yocoLoyaltyClearCache(); return false;">%s</a>',
                __('Wis Cache', 'yoco-loyalty')
            );
        }
        
        return $links;
    }
    
    /**
     * Krijg remote versie informatie - VERBETERDE VERSIE
     */
    public function get_remote_version() {
        // Check cache eerst
        $cache_key = 'yoco_loyalty_remote_version_' . md5($this->github_username . $this->github_repo);
        $cached_version = get_transient($cache_key);
        
        // FIX: Verkort cache tijd voor testing
        if ($cached_version !== false && !defined('WP_DEBUG')) {
            return $cached_version;
        }
        
        // GitHub API URLs
        $release_url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_username,
            $this->github_repo
        );
        
        $contents_url = sprintf(
            'https://api.github.com/repos/%s/%s/contents/%s?ref=%s',
            $this->github_username,
            $this->github_repo,
            basename($this->plugin_file),
            $this->github_branch
        );
        
        // Probeer eerst releases API
        $version_info = $this->fetch_github_data($release_url);
        
        if ($version_info && !empty($version_info['tag_name'])) {
            $version_data = array(
                'version' => ltrim($version_info['tag_name'], 'v'), // FIX: verwijder v prefix
                'download_url' => $version_info['zipball_url'],
                'last_updated' => $version_info['published_at'],
                'requires' => $this->plugin_data['RequiresWP'] ?: '5.0',
                'tested' => $this->plugin_data['TestedUpTo'] ?: get_bloginfo('version'),
                'requires_php' => $this->plugin_data['RequiresPHP'] ?: '7.4',
                'source' => 'release'
            );
            
            // Cache voor 6 uur (was 12 uur)
            set_transient($cache_key, $version_data, 6 * HOUR_IN_SECONDS);
            return $version_data;
        }
        
        // Fallback: probeer plugin bestand direct te lezen
        $contents_info = $this->fetch_github_data($contents_url);
        
        if ($contents_info && !empty($contents_info['content'])) {
            $file_content = base64_decode($contents_info['content']);
            
            // Parse plugin header voor versie
            if (preg_match('/Version:\s*(.+)/', $file_content, $matches)) {
                $version_data = array(
                    'version' => trim($matches[1]),
                    'download_url' => sprintf(
                        'https://github.com/%s/%s/archive/refs/heads/%s.zip',
                        $this->github_username,
                        $this->github_repo,
                        $this->github_branch
                    ),
                    'last_updated' => $contents_info['sha'] ? date('Y-m-d H:i:s') : '',
                    'requires' => $this->plugin_data['RequiresWP'] ?: '5.0',
                    'tested' => $this->plugin_data['TestedUpTo'] ?: get_bloginfo('version'),
                    'requires_php' => $this->plugin_data['RequiresPHP'] ?: '7.4',
                    'source' => 'branch'
                );
                
                // Cache voor 6 uur
                set_transient($cache_key, $version_data, 6 * HOUR_IN_SECONDS);
                return $version_data;
            }
        }
        
        return false;
    }
    
    /**
     * Fetch data van GitHub API
     */
    private function fetch_github_data($url) {
        $args = array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'YoCo-Loyalty-Plugin/1.0'
            )
        );
        
        // Voeg GitHub token toe als beschikbaar
        if ($this->github_token) {
            $args['headers']['Authorization'] = 'token ' . $this->github_token;
        }
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if (200 !== $code) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return $data;
    }
    
    /**
     * FIX: Verbeterde cache clearing functie
     */
    public function clear_cache() {
        // Wis alle relevante transients
        $cache_key = 'yoco_loyalty_remote_version_' . md5($this->github_username . $this->github_repo);
        delete_transient($cache_key);
        delete_site_transient('update_plugins');
        
        // Wis WordPress plugin cache
        wp_cache_delete('plugins', 'plugins');
        
        // Force refresh van plugin updates
        wp_clean_update_cache();
        
        // Verwijder alle gerelateerde cache
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_yoco_loyalty_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_yoco_loyalty_%'");
    }
    
    /**
     * Get debug information
     */
    public function get_debug_info() {
        $remote_version = $this->get_remote_version();
        
        return array(
            'current_version' => $this->plugin_data['Version'],
            'remote_version' => $remote_version ? $remote_version['version'] : 'Onbekend',
            'github_username' => $this->github_username,
            'github_repo' => $this->github_repo,
            'github_branch' => $this->github_branch,
            'plugin_basename' => $this->plugin_basename,
            'plugin_slug' => $this->plugin_slug,
            'cache_key' => 'yoco_loyalty_remote_version_' . md5($this->github_username . $this->github_repo),
            'remote_data' => $remote_version
        );
    }
}