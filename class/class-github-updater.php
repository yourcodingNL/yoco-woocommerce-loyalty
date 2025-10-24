<?php
/**
 * GitHub Plugin Updater
 * 
 * Zorgt voor automatische plugin updates vanuit een GitHub repository
 */

// Voorkom directe toegang
if (!defined('ABSPATH')) {
    exit;
}

class WC_Loyalty_GitHub_Updater {
    
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
                'url' => $this->plugin_data['PluginURI'],
                'package' => $remote_version['download_url'],
                'tested' => $remote_version['tested'],
                'requires_php' => $remote_version['requires_php'],
                'compatibility' => new stdClass(),
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
                'changelog' => $this->get_changelog(),
                'description' => $this->plugin_data['Description'],
            ),
            'download_link' => $remote_version['download_url'],
            'trunk' => $remote_version['download_url'],
            'requires' => $remote_version['requires'],
            'tested' => $remote_version['tested'],
            'requires_php' => $remote_version['requires_php'],
            'banners' => array(),
            'icons' => array(),
        );
    }
    
    /**
     * Download package
     */
    public function download_package($reply, $package, $upgrader) {
        if (strpos($package, 'github.com') === false) {
            return $reply;
        }
        
        // Download van GitHub
        $args = array(
            'timeout' => 300,
        );
        
        // Voeg token toe als beschikbaar
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
            return new WP_Error('download_failed', sprintf(__('Download failed met HTTP code %d', 'wc-loyalty'), $code));
        }
        
        $temp_file = download_url($package);
        
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }
        
        return $temp_file;
    }
    
    /**
     * Plugin row meta
     */
    public function plugin_row_meta($links, $file) {
        if ($file === $this->plugin_basename) {
            $links[] = sprintf(
                '<a href="%s" target="_blank">%s</a>',
                esc_url($this->plugin_data['PluginURI']),
                __('GitHub Repository', 'wc-loyalty')
            );
            
            // Check voor update knop
            $links[] = sprintf(
                '<a href="%s">%s</a>',
                wp_nonce_url(admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($this->plugin_basename)), 'upgrade-plugin_' . $this->plugin_basename),
                __('Check voor Updates', 'wc-loyalty')
            );
        }
        
        return $links;
    }
    
    /**
     * Krijg remote versie informatie
     */
    private function get_remote_version() {
        // Check cache eerst
        $cache_key = 'wc_loyalty_remote_version_' . md5($this->github_username . $this->github_repo);
        $cached_version = get_transient($cache_key);
        
        if ($cached_version !== false) {
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
                'version' => ltrim($version_info['tag_name'], 'v'),
                'download_url' => $version_info['zipball_url'],
                'last_updated' => $version_info['published_at'],
                'requires' => $this->plugin_data['RequiresWP'] ?: '5.0',
                'tested' => $this->plugin_data['TestedUpTo'] ?: get_bloginfo('version'),
                'requires_php' => $this->plugin_data['RequiresPHP'] ?: '7.4',
            );
        } else {
            // Fallback: haal versie uit plugin bestand
            $file_info = $this->fetch_github_data($contents_url);
            
            if (!$file_info || empty($file_info['content'])) {
                return false;
            }
            
            $content = base64_decode($file_info['content']);
            preg_match('/Version:\s*(.+)/', $content, $matches);
            
            if (empty($matches[1])) {
                return false;
            }
            
            $version_data = array(
                'version' => trim($matches[1]),
                'download_url' => sprintf(
                    'https://github.com/%s/%s/archive/%s.zip',
                    $this->github_username,
                    $this->github_repo,
                    $this->github_branch
                ),
                'last_updated' => current_time('mysql'),
                'requires' => $this->plugin_data['RequiresWP'] ?: '5.0',
                'tested' => $this->plugin_data['TestedUpTo'] ?: get_bloginfo('version'),
                'requires_php' => $this->plugin_data['RequiresPHP'] ?: '7.4',
            );
        }
        
        // Cache voor 12 uur
        set_transient($cache_key, $version_data, 12 * HOUR_IN_SECONDS);
        
        return $version_data;
    }
    
    /**
     * Haal data op van GitHub API
     */
    private function fetch_github_data($url) {
        $args = array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            ),
        );
        
        // Voeg GitHub token toe indien beschikbaar
        if ($this->github_token) {
            $args['headers']['Authorization'] = 'token ' . $this->github_token;
        }
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            error_log('GitHub API Error: ' . $response->get_error_message());
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if (200 !== $code) {
            error_log('GitHub API HTTP Error: ' . $code);
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('GitHub API JSON Error: ' . json_last_error_msg());
            return false;
        }
        
        return $data;
    }
    
    /**
     * Krijg changelog van GitHub
     */
    private function get_changelog() {
        $changelog_url = sprintf(
            'https://api.github.com/repos/%s/%s/contents/CHANGELOG.md?ref=%s',
            $this->github_username,
            $this->github_repo,
            $this->github_branch
        );
        
        $changelog_data = $this->fetch_github_data($changelog_url);
        
        if ($changelog_data && !empty($changelog_data['content'])) {
            return base64_decode($changelog_data['content']);
        }
        
        return __('Geen changelog beschikbaar.', 'wc-loyalty');
    }
    
    /**
     * Clear update cache
     */
    public function clear_cache() {
        $cache_key = 'wc_loyalty_remote_version_' . md5($this->github_username . $this->github_repo);
        delete_transient($cache_key);
    }
}
