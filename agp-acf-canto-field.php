<?php
/**
 * Plugin Name: AGP ACF Canto Field
 * Description: A custom Advanced Custom Fields field type for integrating with Canto digital asset management. Supports direct document URLs and multiple asset formats with enhanced URL pattern recognition.
 * Version: 2.4.0
 * Author: AGP https://teamallegiance.com
 * License: GPL v2 or later
 * Text Domain: agp-acf-canto-field
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ACF_CANTO_FIELD_VERSION', '2.4.0');
define('ACF_CANTO_FIELD_PLUGIN_FILE', __FILE__);
define('ACF_CANTO_FIELD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ACF_CANTO_FIELD_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Helper function to extract asset ID from various URL formats
 *
 * @param string $url The URL to extract asset ID from
 * @return string|false Asset ID if found, false otherwise
 */
function acf_canto_extract_asset_id($url) {
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    
    // Pattern 1: Direct document URL - /direct/document/ASSET_ID/TOKEN/original
    if (preg_match('/\/direct\/document\/([^\/\?]+)\/[^\/]+\/original/', $url, $matches)) {
        return $matches[1];
    }
    
    // Pattern 2: Direct document URL without /original - /direct/document/ASSET_ID
    if (preg_match('/\/direct\/document\/([^\/\?]+)/', $url, $matches)) {
        return $matches[1];
    }
    
    // Pattern 3: API binary URL - /api_binary/v1/document/ASSET_ID
    if (preg_match('/\/api_binary\/v1\/document\/([^\/\?]+)/', $url, $matches)) {
        return $matches[1];
    }
    
    // Pattern 4: Any other document URL with recognizable ID pattern
    if (preg_match('/\/document\/([a-zA-Z0-9_-]{15,})/', $url, $matches)) {
        return $matches[1];
    }
    
    return false;
}

/**
 * Helper function to get asset data with URL fallback
 *
 * @param string $identifier Can be asset ID, download URL, or filename
 * @return array|false Asset data if found, false otherwise
 */
function acf_canto_get_asset($identifier) {
    if (empty($identifier)) {
        return false;
    }
    
    if (!class_exists('ACF_Field_Canto')) {
        return false;
    }
    
    $field = new ACF_Field_Canto();
    
    // If it looks like a URL, try URL-based lookup
    if (filter_var($identifier, FILTER_VALIDATE_URL)) {
        $asset_data = $field->find_asset_by_download_url($identifier);
        if ($asset_data) {
            return $asset_data;
        }
    }
    
    // If it looks like an asset ID (alphanumeric, longer than 10 chars), try direct lookup
    if (preg_match('/^[a-zA-Z0-9_-]+$/', $identifier) && strlen($identifier) > 10) {
        $asset_data = $field->get_canto_asset_data($identifier);
        if ($asset_data) {
            return $asset_data;
        }
    }
    
    // If it contains a dot, assume it's a filename and try filename search
    if (strpos($identifier, '.') !== false) {
        return $field->find_asset_by_filename($identifier);
    }
    
    return false;
}

/**
 * Helper function to find Canto asset by filename
 *
 * @param string $filename The filename to search for
 * @return array|false Asset data if found, false otherwise
 */
function acf_canto_find_asset_by_filename($filename) {
    if (!class_exists('ACF_Field_Canto')) {
        return false;
    }
    
    // Create temporary instance to use the search method
    $field = new ACF_Field_Canto();
    return $field->find_asset_by_filename($filename);
}




define('ACF_CANTO_FIELD_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main ACF Canto Field Plugin Class
 */
class ACF_Canto_Field_Plugin
{
    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Plugin settings
     */
    public $settings;

    /**
     * Get instance of this class
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->settings = array(
            'version' => ACF_CANTO_FIELD_VERSION,
            'url'     => ACF_CANTO_FIELD_PLUGIN_URL,
            'path'    => ACF_CANTO_FIELD_PLUGIN_PATH
        );

        // Initialize plugin when ACF is ready
        add_action('init', array($this, 'init'), 20);
        
        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    /**
     * Initialize the plugin
     */
    public function init()
    {
        // Load text domain
        load_plugin_textdomain('agp-acf-canto-field', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Check dependencies
        if (!$this->check_dependencies()) {
            return;
        }

        // Load helper classes
        require_once ACF_CANTO_FIELD_PLUGIN_PATH . 'includes/class-acf-canto-logger.php';
        require_once ACF_CANTO_FIELD_PLUGIN_PATH . 'includes/class-acf-canto-api.php';
        require_once ACF_CANTO_FIELD_PLUGIN_PATH . 'includes/class-acf-canto-asset-formatter.php';

        // Register the field type using the modern ACF method
        if (function_exists('acf_register_field_type')) {
            require_once ACF_CANTO_FIELD_PLUGIN_PATH . 'includes/class-acf-field-canto.php';
            acf_register_field_type('ACF_Field_Canto');
        }
        
        // Include AJAX handler
        $this->include_ajax_handler();
    }

    /**
     * Check plugin dependencies
     */
    private function check_dependencies()
    {
        $dependencies_met = true;

        // Check if ACF is active
        if (!function_exists('acf_register_field_type')) {
            add_action('admin_notices', array($this, 'acf_missing_notice'));
            $dependencies_met = false;
        }

        // Check if Canto plugin is active (optional - field will show warning if not available)
        if (!function_exists('Canto')) {
            add_action('admin_notices', array($this, 'canto_missing_notice'));
            // Don't block field registration for missing Canto - just show warning
        }

        return $dependencies_met;
    }

    /**
     * Include AJAX handler and thumbnail proxy
     */
    public function include_ajax_handler()
    {
        require_once ACF_CANTO_FIELD_PLUGIN_PATH . 'includes/class-acf-canto-ajax.php';
        new ACF_Canto_AJAX_Handler();
        
        require_once ACF_CANTO_FIELD_PLUGIN_PATH . 'includes/class-acf-canto-thumbnail-proxy.php';
        new ACF_Canto_Thumbnail_Proxy();
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        // Set activation flag
        add_option('acf_canto_field_activated', true);
        
        // Flush rewrite rules to activate our thumbnail proxy
        flush_rewrite_rules();
        
        // Clear any caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        // Clean up transients using improved cache clearing
        if (class_exists('ACF_Canto_API')) {
            $logger = new ACF_Canto_Logger();
            $api = new ACF_Canto_API($logger);
            $api->clear_cache();
        } else {
            // Fallback method
            global $wpdb;
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_canto_asset_%' 
                 OR option_name LIKE '_transient_timeout_canto_asset_%'
                 OR option_name LIKE '_transient_acf_canto_%' 
                 OR option_name LIKE '_transient_timeout_acf_canto_%'"
            );
        }
        
        // Flush rewrite rules to remove our thumbnail proxy
        flush_rewrite_rules();
        
        // Remove activation flag
        delete_option('acf_canto_field_activated');
    }

    /**
     * Admin notices
     */
    public function admin_notices()
    {
        // Show activation notice
        if (get_option('acf_canto_field_activated')) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>' . esc_html__('AGP ACF Canto Field', 'agp-acf-canto-field') . '</strong> ' . esc_html__('plugin has been activated successfully!', 'agp-acf-canto-field') . '</p>';
            echo '</div>';
            delete_option('acf_canto_field_activated');
        }
    }

    /**
     * ACF missing notice
     */
    public function acf_missing_notice()
    {
        echo '<div class="notice notice-error">';
        echo '<p><strong>' . esc_html__('AGP ACF Canto Field', 'agp-acf-canto-field') . '</strong> ' . esc_html__('requires Advanced Custom Fields (ACF) to be installed and activated.', 'agp-acf-canto-field') . '</p>';
        echo '</div>';
    }

    /**
     * Canto missing notice
     */
    public function canto_missing_notice()
    {
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>' . esc_html__('AGP ACF Canto Field', 'agp-acf-canto-field') . '</strong> ' . esc_html__('The Canto plugin is not installed or configured. The field will be available but will show a configuration message until Canto is properly set up.', 'agp-acf-canto-field') . '</p>';
        echo '</div>';
    }

    /**
     * Add a note about Canto deprecation warnings if they appear
     */
    public function canto_deprecation_info()
    {
        if (function_exists('Canto') && defined('WP_DEBUG') && WP_DEBUG) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>' . esc_html__('Note:', 'agp-acf-canto-field') . '</strong> ' . esc_html__('You may see deprecation warnings from the Canto plugin in your debug log. These are harmless and come from the official Canto plugin, not the AGP ACF Canto Field plugin. They will be fixed in a future Canto plugin update.', 'agp-acf-canto-field') . '</p>';
            echo '</div>';
        }
    }
}

/**
 * Initialize the plugin
 */
function acf_canto_field_plugin()
{
    return ACF_Canto_Field_Plugin::get_instance();
}

// Start the plugin
acf_canto_field_plugin();
