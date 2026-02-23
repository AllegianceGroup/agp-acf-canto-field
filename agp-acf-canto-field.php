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

        // Load the thumbnail proxy early so its init hook (priority 1)
        // fires before SAML/SSO plugins can intercept the request.
        require_once ACF_CANTO_FIELD_PLUGIN_PATH . 'includes/class-acf-canto-thumbnail-proxy.php';

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
     * Include AJAX handler
     */
    public function include_ajax_handler()
    {
        require_once ACF_CANTO_FIELD_PLUGIN_PATH . 'includes/class-acf-canto-ajax.php';
        new ACF_Canto_AJAX_Handler();
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        add_option('acf_canto_field_activated', true);
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        // Clean up transients
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_acf_canto_%'
             OR option_name LIKE '_transient_timeout_acf_canto_%'"
        );

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
