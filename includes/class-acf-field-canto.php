<?php
/**
 * ACF Canto Field Class
 *
 * A custom ACF field that integrates with the Canto plugin to allow
 * users to select assets directly from their Canto library.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if class already exists
if (!class_exists('ACF_Field_Canto')) :

class ACF_Field_Canto extends acf_field
{
    /**
     * Controls field type visibility in REST requests.
     *
     * @var bool
     */
    public $show_in_rest = true;

    /**
     * Environment values relating to the plugin.
     *
     * @var array $env Plugin context such as 'url' and 'version'.
     */
    private $env;
    
    /**
     * Logger instance
     *
     * @var ACF_Canto_Logger
     */
    private $logger;
    
    /**
     * API helper instance
     *
     * @var ACF_Canto_API
     */
    private $api;
    
    /**
     * Asset formatter instance
     *
     * @var ACF_Canto_Asset_Formatter
     */
    private $formatter;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Field type name (single word, no spaces, underscores allowed)
        $this->name = 'canto';
        
        // Field type label (multiple words, can include spaces, visible when selecting a field type)
        $this->label = __('Canto Asset', 'acf-canto-field');
        
        // Field type category (basic | content | choice | relational | jquery | layout | CUSTOM GROUP NAME)
        $this->category = 'content';
        
        // Field type description
        $this->description = __('Select assets from your Canto library.', 'acf-canto-field');
        
        // Field defaults
        $this->defaults = array(
            'return_format' => 'object',
        );
        
        // JavaScript strings
        $this->l10n = array(
            'error' => __('Error! Please select a Canto asset.', 'acf-canto-field'),
            'select' => __('Select Canto Asset', 'acf-canto-field'),
            'edit' => __('Edit Asset', 'acf-canto-field'),
            'remove' => __('Remove Asset', 'acf-canto-field'),
            'loading' => __('Loading...', 'acf-canto-field'),
            'no_assets' => __('No assets found.', 'acf-canto-field'),
            'search_placeholder' => __('Search assets...', 'acf-canto-field'),
            'select_asset_button' => __('Select Asset', 'acf-canto-field'),
            'cancel' => __('Cancel', 'acf-canto-field'),
            'show_details' => __('Show Details', 'acf-canto-field'),
            'hide_details' => __('Hide Details', 'acf-canto-field'),
        );
        
        // Environment settings
        $this->env = array(
            'url'     => ACF_CANTO_FIELD_PLUGIN_URL,
            'version' => ACF_CANTO_FIELD_VERSION,
        );
        
        // Initialize helper classes
        $this->logger = new ACF_Canto_Logger();
        $this->api = new ACF_Canto_API($this->logger);
        $this->formatter = new ACF_Canto_Asset_Formatter($this->logger, $this->api);
        
        // Call parent constructor
        parent::__construct();
    }
    
    /**
     * Create extra settings for the field
     *
     * @param array $field The field being edited
     */
    public function render_field_settings($field)
    {
        acf_render_field_setting($field, array(
            'label'        => __('Return Format', 'acf-canto-field'),
            'instructions' => __('Specify the returned value on front end', 'acf-canto-field'),
            'type'         => 'select',
            'name'         => 'return_format',
            'choices'      => array(
                'object'    => __('Canto Asset Object', 'acf-canto-field'),
                'id'        => __('Canto Asset ID', 'acf-canto-field'),
                'url'       => __('Asset URL', 'acf-canto-field'),
            )
        ));
    }
    
    /**
     * Create the HTML interface for the field
     *
     * @param array $field The field being rendered
     */
    public function render_field($field)
    {
        // Check if Canto is available
        if (!$this->is_canto_available()) {
            $this->render_canto_error();
            return;
        }
        
        $value = $field['value'];
        $canto_data = $this->get_asset_data_for_field($value);
        
        
        $this->render_field_html($field, $value, $canto_data);
    }
    
    /**
     * Check if Canto is available and configured
     *
     * @return bool
     */
    private function is_canto_available()
    {
        return function_exists('Canto') && $this->api->is_configured();
    }
    
    /**
     * Render error message when Canto is not available
     */
    private function render_canto_error()
    {
        echo '<div class="acf-canto-error">';
        echo '<p>' . __('Canto plugin is not configured or not available.', 'acf-canto-field') . '</p>';
        
        $errors = $this->api->get_config_errors();
        if (!function_exists('Canto')) {
            $errors[] = __('Canto plugin not found.', 'acf-canto-field');
        }
        
        foreach ($errors as $error) {
            echo '<p><em>' . esc_html($error) . '</em></p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Get asset data for field value
     *
     * @param mixed $value
     * @return array|false
     */
    private function get_asset_data_for_field($value)
    {
        if (empty($value)) {
            return false;
        }
        
        $download_url = (string) $value;
        
        // Check if it's test format (CANTO_id_suffix)
        if (strpos($download_url, 'CANTO_') === 0) {
            return $this->get_asset_from_test_format($download_url);
        }
        
        // Regular download URL
        return $this->find_asset_by_download_url($download_url);
    }
    
    /**
     * Extract asset data from test format
     *
     * @param string $test_format
     * @return array|false
     */
    private function get_asset_from_test_format($test_format)
    {
        if (preg_match('/^CANTO_([^_]+)_/', $test_format, $matches)) {
            $asset_id = $matches[1];
            return $this->get_canto_asset_data($asset_id);
        }
        
        return false;
    }
    
    /**
     * Render field HTML
     *
     * @param array $field
     * @param mixed $value
     * @param array|false $canto_data
     */
    private function render_field_html($field, $value, $canto_data)
    {
        ?>
        <div class="acf-canto-field" data-field-name="<?php echo esc_attr($field['name']); ?>">
            <input type="hidden" name="<?php echo esc_attr($field['name']); ?>" value="<?php echo esc_attr($value); ?>" />
            
            <div class="acf-canto-container">
                <?php if ($canto_data): ?>
                    <?php $this->render_asset_preview($canto_data); ?>
                <?php else: ?>
                    <?php $this->render_asset_placeholder(); ?>
                <?php endif; ?>
            </div>
            
            <?php $this->render_asset_modal(); ?>
        </div>
        <?php
    }
    
    /**
     * Render asset preview
     *
     * @param array $canto_data
     */
    private function render_asset_preview($canto_data)
    {
        ?>
        <div class="acf-canto-preview">
            <div class="acf-canto-preview-image">
                <?php if (isset($canto_data['thumbnail'])): ?>
                    <img src="<?php echo esc_url($canto_data['thumbnail']); ?>" alt="<?php echo esc_attr($canto_data['name']); ?>" />
                <?php endif; ?>
            </div>
            <div class="acf-canto-preview-details">
                <h4><?php echo esc_html($canto_data['name']); ?></h4>
                <?php if (isset($canto_data['dimensions']) && $canto_data['dimensions']): ?>
                    <p><?php echo esc_html($canto_data['dimensions']); ?></p>
                <?php endif; ?>
                <?php if (isset($canto_data['size']) && $canto_data['size']): ?>
                    <p><?php echo esc_html($canto_data['size']); ?></p>
                <?php endif; ?>
            </div>
            <div class="acf-canto-actions">
                <button type="button" class="button acf-canto-edit"><?php echo esc_html($this->l10n['edit']); ?></button>
                <button type="button" class="button acf-canto-remove"><?php echo esc_html($this->l10n['remove']); ?></button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render asset placeholder
     */
    private function render_asset_placeholder()
    {
        ?>
        <div class="acf-canto-placeholder">
            <button type="button" class="button button-primary acf-canto-select">
                <?php echo esc_html($this->l10n['select']); ?>
            </button>
        </div>
        <?php
    }
    
    /**
     * Render asset selection modal
     */
    private function render_asset_modal()
    {
        ?>
        <!-- Modal for asset selection -->
        <div class="acf-canto-modal" style="display: none;">
            <div class="acf-canto-modal-content">
                <div class="acf-canto-modal-header">
                    <h3><?php echo esc_html($this->l10n['select']); ?></h3>
                    <button type="button" class="acf-canto-modal-close">&times;</button>
                </div>
                <div class="acf-canto-modal-body">
                    <div class="acf-canto-navigation">
                        <div class="acf-canto-nav-tabs">
                            <button type="button" class="acf-canto-nav-tab active" data-view="search"><?php _e('Search', 'acf-canto-field'); ?></button>
                            <button type="button" class="acf-canto-nav-tab" data-view="browse"><?php _e('Browse', 'acf-canto-field'); ?></button>
                        </div>
                        <div class="acf-canto-view-toggle">
                            <button type="button" class="acf-canto-view-toggle-btn active" data-view-mode="grid" title="<?php _e('Grid View', 'acf-canto-field'); ?>">
                                <span class="dashicons dashicons-grid-view"></span>
                            </button>
                            <button type="button" class="acf-canto-view-toggle-btn" data-view-mode="list" title="<?php _e('List View', 'acf-canto-field'); ?>">
                                <span class="dashicons dashicons-list-view"></span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="acf-canto-content">
                        <!-- Search View -->
                        <div class="acf-canto-view acf-canto-search-view active">
                            <div class="acf-canto-search">
                                <div class="acf-canto-search-input-wrapper">
                                    <input type="text" class="acf-canto-search-input" placeholder="<?php echo esc_attr($this->l10n['search_placeholder']); ?>" />
                                    <button type="button" class="acf-canto-search-clear" title="<?php _e('Clear search', 'acf-canto-field'); ?>" style="display: none;">&times;</button>
                                </div>
                                <button type="button" class="button acf-canto-search-btn"><?php _e('Search', 'acf-canto-field'); ?></button>
                            </div>
                            <div class="acf-canto-results">
                                <div class="acf-canto-loading" style="display: none;">
                                    <?php echo esc_html($this->l10n['loading']); ?>
                                </div>
                                <div class="acf-canto-assets-grid"></div>
                            </div>
                        </div>
                        
                        <!-- Browse View -->
                        <div class="acf-canto-view acf-canto-browse-view">
                            <div class="acf-canto-browse-layout">
                                <div class="acf-canto-tree-sidebar">
                                    <div class="acf-canto-tree-header">
                                        <h4><?php _e('Albums & Folders', 'acf-canto-field'); ?></h4>
                                        <button type="button" class="button-link acf-canto-tree-refresh" title="<?php _e('Refresh', 'acf-canto-field'); ?>">↻</button>
                                    </div>
                                    <div class="acf-canto-tree-loading" style="display: none;">
                                        <?php echo esc_html($this->l10n['loading']); ?>
                                    </div>
                                    <div class="acf-canto-tree-container">
                                        <ul class="acf-canto-tree-list"></ul>
                                    </div>
                                </div>
                                <div class="acf-canto-browse-content">
                                    <div class="acf-canto-browse-header">
                                        <h4 class="acf-canto-current-path"><?php _e('All Assets', 'acf-canto-field'); ?></h4>
                                        <button type="button" class="button-link acf-canto-browse-refresh" title="<?php _e('Refresh', 'acf-canto-field'); ?>">↻</button>
                                    </div>
                                    <div class="acf-canto-browse-loading" style="display: none;">
                                        <?php echo esc_html($this->l10n['loading']); ?>
                                    </div>
                                    <div class="acf-canto-browse-assets"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="acf-canto-modal-footer">
                    <button type="button" class="button button-primary acf-canto-confirm-selection" disabled><?php echo esc_html($this->l10n['select_asset_button']); ?></button>
                    <button type="button" class="button acf-canto-cancel"><?php echo esc_html($this->l10n['cancel']); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue scripts and styles for the field
     */
    public function input_admin_enqueue_scripts()
    {
        $url = trailingslashit($this->env['url']);
        $version = $this->env['version'];
        
        // Register & include JS
        wp_register_script(
            'acf-input-canto',
            "{$url}assets/js/input.js",
            array('acf-input'),
            $version
        );
        wp_enqueue_script('acf-input-canto');
        
        // Register & include CSS
        wp_register_style(
            'acf-input-canto',
            "{$url}assets/css/input.css",
            array('acf-input'),
            $version
        );
        wp_enqueue_style('acf-input-canto');
        
        // Localize script with Canto data
        wp_localize_script('acf-input-canto', 'acf_canto', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('acf_canto_nonce'),
            'plugin_url' => ACF_CANTO_FIELD_PLUGIN_URL,
            'l10n' => $this->l10n,
            'canto_domain' => get_option('fbc_flight_domain'),
            'canto_available' => function_exists('Canto') && get_option('fbc_app_token')
        ));
    }
    
    /**
     * Format the field value for frontend display
     *
     * By default, constructs asset data from the stored URL without calling
     * the Canto API. Thumbnails are served lazily by the canto-thumbnail
     * proxy route when the browser requests them.
     *
     * To re-enable live API calls, use the filter:
     *   add_filter('acf_canto_format_value_use_api', '__return_true');
     *
     * @param mixed $value The value found in the database
     * @param int $post_id The post ID from which the value was loaded
     * @param array $field The field array holding all the field options
     * @return mixed
     */
    public function format_value($value, $post_id, $field)
    {
        if (empty($value)) {
            return false;
        }

        $use_api = apply_filters('acf_canto_format_value_use_api', false, $value, $post_id, $field);

        if ($use_api) {
            $asset_data = $this->get_asset_data_for_field($value);
        } else {
            $asset_data = $this->format_value_from_url($value);
        }

        if (!$asset_data) {
            $this->logger->warning('Asset data not found for field value', array('value' => $value, 'post_id' => $post_id));
            return false;
        }

        return $this->formatter->prepare_return_value($asset_data, $field);
    }

    /**
     * Construct asset data from a download URL without calling the Canto API.
     *
     * Extracts the asset ID and scheme from the URL, then builds the same
     * data structure that format_from_api() would return. Thumbnail URLs
     * point to the local canto-thumbnail proxy route.
     *
     * @param mixed $value The stored field value (download URL or test format)
     * @return array|false Asset data array, or false on failure
     */
    private function format_value_from_url($value)
    {
        $download_url = (string) $value;

        // Handle test format (CANTO_id_suffix) — fall back to API for these
        if (strpos($download_url, 'CANTO_') === 0) {
            return $this->get_asset_data_for_field($value);
        }

        // api_binary URLs require authentication and can't be used as direct
        // browser downloads. Fall back to the API to get a directUrlOriginal.
        if (strpos($download_url, '/api_binary/') !== false) {
            return $this->get_asset_data_for_field($value);
        }

        $asset_id = $this->extract_asset_id_from_url($download_url);
        if (!$asset_id) {
            return false;
        }

        $scheme = $this->extract_scheme_from_url($download_url);
        $name = $this->extract_name_from_url($download_url);

        // Build the same structure as format_from_api() / build_base_asset_data()
        return array(
            'id'           => $asset_id,
            'scheme'       => $scheme,
            'name'         => $name ?: __('Untitled', 'acf-canto-field'),
            'filename'     => $name ?: '',
            'url'          => $download_url,
            'thumbnail'    => $this->api->build_thumbnail_url($asset_id, $scheme),
            'download_url' => $download_url,
            'dimensions'   => '',
            'mime_type'    => '',
            'size'         => '',
            'uploaded'     => '',
            'metadata'     => array(),
        );
    }

    /**
     * Extract the asset scheme (image, video, document) from a URL.
     *
     * @param string $url
     * @return string One of 'image', 'video', 'document'
     */
    private function extract_scheme_from_url($url)
    {
        if (preg_match('/\/(image)\//', $url)) {
            return 'image';
        }
        if (preg_match('/\/(video)\//', $url)) {
            return 'video';
        }
        // Default to document (most assets are PDFs)
        return 'document';
    }

    /**
     * Extract the asset name from a Canto download URL.
     *
     * Checks the 'name' query parameter first (present in direct URLs),
     * then falls back to the URL path basename. Returns empty string
     * for generic basenames like 'original' or 'download'.
     *
     * @param string $url
     * @return string
     */
    private function extract_name_from_url($url)
    {
        // Try query string 'name' parameter first
        $query = parse_url($url, PHP_URL_QUERY);
        if ($query) {
            parse_str($query, $params);
            if (!empty($params['name'])) {
                return $params['name'];
            }
        }

        // Fall back to URL path basename
        $path = parse_url($url, PHP_URL_PATH);
        $basename = $path ? basename($path) : '';

        // Skip generic basenames that aren't real filenames
        if (in_array(strtolower($basename), array('original', 'download', 'preview'), true)) {
            return '';
        }

        return $basename;
    }

    /**
     * Extract asset ID from various URL formats
     *
     * @param string $url The URL to extract asset ID from
     * @return string|false Asset ID if found, false otherwise
     */
    private function extract_asset_id_from_url($url) {
        if (empty($url)) {
            return false;
        }

        // Pattern 1: Direct URL - /direct/(document|image|video)/ASSET_ID/TOKEN/original
        if (preg_match('/\/direct\/(?:document|image|video)\/([^\/\?]+)\/[^\/]+\/original/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern 2: Direct URL without /original - /direct/(document|image|video)/ASSET_ID
        if (preg_match('/\/direct\/(?:document|image|video)\/([^\/\?]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern 3: API binary URL - /api_binary/v1/(advance/)?(image|video|document)/ASSET_ID
        if (preg_match('/\/api_binary\/v1\/(?:advance\/)?(?:image|video|document)\/([a-zA-Z0-9]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern 4: Any other asset URL with recognizable ID pattern
        if (preg_match('/\/(?:document|image|video)\/([a-zA-Z0-9_-]{15,})/', $url, $matches)) {
            return $matches[1];
        }

        return false;
    }

    /**
     * Find asset by download URL
     *
     * @param string $download_url The download URL to search for
     * @return array|false Asset data if found, false otherwise
     */
    public function find_asset_by_download_url($download_url)
    {
        if (empty($download_url)) {
            return false;
        }

        $asset_id = $this->extract_asset_id_from_url($download_url);

        if ($asset_id) {
            return $this->get_canto_asset_data($asset_id);
        }

        $this->logger->warning('Could not extract asset ID from URL', array('url' => $download_url));
        return false;
    }

    /**
     * Search for asset by filename
     *
     * @param string $filename The filename to search for
     * @return array|false Asset data if found, false otherwise
     */
    public function find_asset_by_filename($filename)
    {
        if (empty($filename)) {
            return false;
        }

        $result = $this->api->search_assets($filename, array('limit' => 50));

        if (is_wp_error($result) || !isset($result['results']) || empty($result['results'])) {
            return false;
        }

        // Priority 1: Exact filename match
        foreach ($result['results'] as $item) {
            $asset_data = $this->formatter->format_from_search($item);
            if ($asset_data && $asset_data['filename'] === $filename) {
                return $asset_data;
            }
        }

        // Priority 2: Exact name match
        foreach ($result['results'] as $item) {
            $asset_data = $this->formatter->format_from_search($item);
            if ($asset_data && $asset_data['name'] === $filename) {
                return $asset_data;
            }
        }

        // Priority 3: First result as fuzzy fallback
        $first = reset($result['results']);
        return $this->formatter->format_from_search($first) ?: false;
    }
    
    /**
     * Validate the field value
     *
     * @param bool $valid Current validation status
     * @param mixed $value The $_POST value
     * @param array $field The field array holding all the field options
     * @param string $input The corresponding input name for $_POST value
     * @return bool|string
     */
    public function validate_value($valid, $value, $field, $input)
    {
        if ($field['required'] && empty($value)) {
            $valid = __('This field is required.', 'acf-canto-field');
        }

        return $valid;
    }
    
    /**
     * Update field value before saving to database
     *
     * @param mixed $value The value found in the $_POST array
     * @param int $post_id The post ID from which the value was loaded
     * @param array $field The field array holding all the field options
     * @return mixed
     */
    public function update_value($value, $post_id, $field)
    {
        $this->logger->debug('Updating field value', array(
            'value' => $value,
            'post_id' => $post_id,
            'field_name' => isset($field['name']) ? $field['name'] : 'unknown'
        ));
        
        if (empty($value) || !is_string($value)) {
            return '';
        }
        
        $value = trim($value);
        
        // Validate URL format
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $this->sanitize_url_value($value);
        }
        
        // Handle legacy or test format values
        if (!empty($value)) {
            $this->logger->info('Saving non-URL value (legacy or test format)', array('value' => $value));
            return $value;
        }
        
        return '';
    }
    
    /**
     * Sanitize URL value for database storage
     *
     * @param string $url
     * @return string
     */
    private function sanitize_url_value($url)
    {
        // Check URL length for database compatibility
        $max_length = 2000; // Safe limit for most database configurations
        
        if (strlen($url) > $max_length) {
            $this->logger->warning('URL too long for database storage', array(
                'length' => strlen($url),
                'max_length' => $max_length,
                'url_preview' => substr($url, 0, 100) . '...'
            ));
            
            return substr($url, 0, $max_length);
        }
        
        return $url;
    }
    
    /**
     * Helper function to get Canto asset data
     *
     * @param string $asset_id The Canto asset ID
     * @return array|false
     */
    public function get_canto_asset_data($asset_id)
    {
        if (empty($asset_id)) {
            $this->logger->warning('Empty asset ID provided');
            return false;
        }
        
        $this->logger->debug('Loading asset data', array('asset_id' => $asset_id));
        
        $result = $this->api->get_asset($asset_id);
        
        if (is_wp_error($result)) {
            $this->logger->error('Failed to get asset data: ' . $result->get_error_message(), array('asset_id' => $asset_id));
            return false;
        }
        
        return $this->formatter->format_from_api($result, $asset_id);
    }
}

endif;
