<?php
/**
 * ACF Canto API Helper Class
 *
 * Handles API communication with Canto service
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ACF_Canto_API
{
    // API constants
    const DEFAULT_API_DOMAIN = 'canto.com';
    const DEFAULT_TIMEOUT = 30;
    const DEFAULT_SEARCH_LIMIT = 50;
    const MAX_SEARCH_LIMIT = 100;
    const CACHE_DURATION = HOUR_IN_SECONDS;
    
    // File type constants
    const FILETYPE_IMAGES = 'GIF|JPG|PNG|SVG|WEBP';
    const FILETYPE_DOCUMENTS = 'DOC|KEY|ODT|PDF|PPT|XLS';
    const FILETYPE_AUDIO = 'MPEG|M4A|OGG|WAV';
    const FILETYPE_VIDEO = 'AVI|MP4|MOV|OGG|VTT|WMV|3GP';
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * API configuration
     */
    private $config;
    
    /**
     * Constructor
     */
    public function __construct($logger = null)
    {
        $this->logger = $logger ?: new ACF_Canto_Logger();
        $this->config = $this->load_api_config();
    }
    
    /**
     * Load API configuration
     *
     * @return array
     */
    private function load_api_config()
    {
        return array(
            'domain' => get_option('fbc_flight_domain'),
            'api_domain' => get_option('fbc_app_api', self::DEFAULT_API_DOMAIN),
            'token' => get_option('fbc_app_token'),
        );
    }
    
    /**
     * Check if API is properly configured
     *
     * @return bool
     */
    public function is_configured()
    {
        return !empty($this->config['domain']) && !empty($this->config['token']);
    }
    
    /**
     * Get API configuration errors
     *
     * @return array Array of error messages
     */
    public function get_config_errors()
    {
        $errors = array();
        
        if (empty($this->config['domain'])) {
            $errors[] = __('Canto domain not configured', 'acf-canto-field');
        }
        
        if (empty($this->config['token'])) {
            $errors[] = __('Canto API token not configured', 'acf-canto-field');
        }
        
        return $errors;
    }
    
    /**
     * Make API request with error handling
     *
     * @param string $endpoint API endpoint
     * @param array $args Request arguments
     * @return array|WP_Error
     */
    public function request($endpoint, $args = array())
    {
        if (!$this->is_configured()) {
            return new WP_Error('api_not_configured', 'Canto API is not properly configured');
        }
        
        $url = $this->build_api_url($endpoint);
        $request_args = $this->prepare_request_args($args);
        
        $this->logger->debug('Making API request to: ' . $url);
        
        try {
            $response = wp_remote_get($url, $request_args);
            
            if (is_wp_error($response)) {
                $this->logger->error('API request failed: ' . $response->get_error_message());
                return $response;
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($http_code !== 200) {
                $error_msg = sprintf('API returned HTTP %d', $http_code);
                $this->logger->error($error_msg . ': ' . $body);
                return new WP_Error('api_http_error', $error_msg, array('code' => $http_code, 'body' => $body));
            }
            
            if (empty($body)) {
                $this->logger->error('API returned empty response');
                return new WP_Error('api_empty_response', 'API returned empty response');
            }
            
            $data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error_msg = 'Invalid JSON response: ' . json_last_error_msg();
                $this->logger->error($error_msg);
                return new WP_Error('api_invalid_json', $error_msg);
            }
            
            if (isset($data['error'])) {
                $error_msg = 'API error: ' . $data['error'];
                $this->logger->error($error_msg);
                return new WP_Error('api_error', $error_msg, $data);
            }
            
            return $data;
            
        } catch (Exception $e) {
            $error_msg = 'API request exception: ' . $e->getMessage();
            $this->logger->error($error_msg);
            return new WP_Error('api_exception', $error_msg);
        }
    }
    
    /**
     * Search for assets
     *
     * @param string $query Search query
     * @param array $options Search options
     * @return array|WP_Error
     */
    public function search_assets($query = '', $options = array())
    {
        $defaults = array(
            'limit' => self::DEFAULT_SEARCH_LIMIT,
            'start' => 0,
            'file_types' => $this->get_all_file_types(),
            'operator' => 'and',
            'sortBy' => 'time',
            'sortDirection' => 'descending',
            'searchInField' => 'filename'
        );
        
        $options = array_merge($defaults, $options);
        $options['limit'] = min($options['limit'], self::MAX_SEARCH_LIMIT);
        
        $cache_key = $this->get_search_cache_key($query, $options);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            $this->logger->debug('Using cached search results for: ' . $query);
            return $cached_result;
        }
        
        $endpoint = 'search';
        $params = array(
            'keyword' => $query,
            'fileType' => $options['file_types'],
            'operator' => $options['operator'],
            'limit' => $options['limit'],
            'start' => $options['start'],
            'sortBy' => $options['sortBy'],
            'sortDirection' => $options['sortDirection'],
            'searchInField' => $options['searchInField']
        );
        
        $endpoint_with_params = add_query_arg($params, $endpoint);
        $result = $this->request($endpoint_with_params);
        
        if (!is_wp_error($result)) {
            set_transient($cache_key, $result, self::CACHE_DURATION);
            $this->logger->debug('Cached search results for: ' . $query);
        }
        
        return $result;
    }
    
    /**
     * Get asset by ID
     *
     * @param string $asset_id Asset ID
     * @param string $scheme Asset scheme (image, video, document)
     * @return array|WP_Error
     */
    public function get_asset($asset_id, $scheme = null)
    {
        if (empty($asset_id)) {
            return new WP_Error('invalid_asset_id', 'Asset ID is required');
        }
        
        $cache_key = $this->get_asset_cache_key($asset_id);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            $this->logger->debug('Using cached asset data for: ' . $asset_id);
            return $cached_result;
        }
        
        $schemes = $scheme ? array($scheme) : array('image', 'video', 'document');
        
        foreach ($schemes as $current_scheme) {
            $endpoint = $current_scheme . '/' . $asset_id;
            $result = $this->request($endpoint);
            
            if (!is_wp_error($result)) {
                set_transient($cache_key, $result, self::CACHE_DURATION);
                $this->logger->debug('Cached asset data for: ' . $asset_id);
                return $result;
            }
        }
        
        $this->logger->warning('Asset not found: ' . $asset_id);
        return new WP_Error('asset_not_found', 'Asset not found', array('asset_id' => $asset_id));
    }
    
    /**
     * Build API URL
     *
     * @param string $endpoint
     * @return string
     */
    private function build_api_url($endpoint)
    {
        $base_url = sprintf(
            'https://%s.%s/api/v1/',
            $this->config['domain'],
            $this->config['api_domain']
        );
        
        return $base_url . ltrim($endpoint, '/');
    }
    
    /**
     * Prepare request arguments
     *
     * @param array $args
     * @return array
     */
    private function prepare_request_args($args = array())
    {
        $defaults = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->config['token'],
                'User-Agent' => 'WordPress ACF Canto Field Plugin',
                'Content-Type' => 'application/json;charset=utf-8'
            ),
            'timeout' => self::DEFAULT_TIMEOUT
        );
        
        return array_merge_recursive($defaults, $args);
    }
    
    /**
     * Get all supported file types
     *
     * @return string
     */
    private function get_all_file_types()
    {
        return implode('|', array(
            self::FILETYPE_IMAGES,
            self::FILETYPE_DOCUMENTS,
            self::FILETYPE_AUDIO,
            self::FILETYPE_VIDEO
        ));
    }
    
    /**
     * Generate cache key for search
     *
     * @param string $query
     * @param array $options
     * @return string
     */
    private function get_search_cache_key($query, $options)
    {
        $key_data = array(
            'query' => $query,
            'limit' => $options['limit'],
            'start' => $options['start'],
            'file_types' => $options['file_types'],
            'operator' => $options['operator']
        );
        
        return 'acf_canto_search_' . md5(serialize($key_data));
    }
    
    /**
     * Generate cache key for asset
     *
     * @param string $asset_id
     * @return string
     */
    private function get_asset_cache_key($asset_id)
    {
        return 'acf_canto_asset_' . $asset_id;
    }
    
    /**
     * Clear all plugin caches
     *
     * @return bool
     */
    public function clear_cache()
    {
        global $wpdb;
        
        $result = $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_acf_canto_%' 
             OR option_name LIKE '_transient_timeout_acf_canto_%'"
        );
        
        $this->logger->info('Cleared Canto API cache');
        return $result !== false;
    }
    
    /**
     * Build download URL for asset
     *
     * @param string $asset_id
     * @param string $scheme
     * @param array $options
     * @return string
     */
    public function build_download_url($asset_id, $scheme, $options = array())
    {
        $base_url = sprintf(
            'https://%s.%s/api_binary/v1/',
            $this->config['domain'],
            $this->config['api_domain']
        );
        
        switch ($scheme) {
            case 'image':
                $url = $base_url . 'advance/image/' . $asset_id . '/download/directuri';
                $defaults = array('type' => 'jpg', 'dpi' => '72');
                $params = array_merge($defaults, $options);
                return add_query_arg($params, $url);
                
            case 'video':
                return $base_url . 'video/' . $asset_id . '/download';
                
            case 'document':
                return $base_url . 'document/' . $asset_id . '/download';
                
            default:
                return '';
        }
    }
    
    /**
     * Build thumbnail URL for asset
     *
     * @param string $asset_id
     * @param string $scheme
     * @return string
     */
    public function build_thumbnail_url($asset_id, $scheme)
    {
        return home_url('canto-thumbnail/' . $scheme . '/' . $asset_id);
    }
}