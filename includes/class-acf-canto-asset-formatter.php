<?php
/**
 * ACF Canto Asset Formatter Class
 *
 * Handles formatting and processing of Canto asset data
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ACF_Canto_Asset_Formatter
{
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * API instance
     */
    private $api;
    
    /**
     * Constructor
     *
     * @param ACF_Canto_Logger $logger
     * @param ACF_Canto_API $api
     */
    public function __construct($logger = null, $api = null)
    {
        $this->logger = $logger ?: new ACF_Canto_Logger();
        $this->api = $api ?: new ACF_Canto_API($this->logger);
    }
    
    /**
     * Format asset data from search results
     *
     * @param array $raw_data Raw asset data from API
     * @return array|false Formatted asset data or false on error
     */
    public function format_from_search($raw_data)
    {
        if (!$this->is_valid_asset_data($raw_data)) {
            $this->logger->warning('Invalid asset data provided for formatting', array('data' => $raw_data));
            return false;
        }
        
        try {
            $scheme = $this->determine_asset_scheme($raw_data);
            $formatted_data = $this->build_base_asset_data($raw_data, $scheme);
            
            $this->populate_metadata($formatted_data, $raw_data);
            $this->populate_urls($formatted_data, $raw_data, $scheme);
            $this->populate_filename($formatted_data, $raw_data, $scheme);
            
            return $formatted_data;
            
        } catch (Exception $e) {
            $this->logger->exception($e, 'Error formatting asset data from search');
            return false;
        }
    }
    
    /**
     * Format asset data from direct API response
     *
     * @param array $raw_data Raw asset data from API
     * @param string $asset_id Asset ID
     * @return array|false Formatted asset data or false on error
     */
    public function format_from_api($raw_data, $asset_id)
    {
        if (!$this->is_valid_asset_data($raw_data)) {
            $this->logger->warning('Invalid asset data provided for formatting', array('asset_id' => $asset_id, 'data' => $raw_data));
            return false;
        }
        
        try {
            $scheme = $this->determine_asset_scheme($raw_data);
            $formatted_data = $this->build_base_asset_data($raw_data, $scheme, $asset_id);
            
            $this->populate_metadata($formatted_data, $raw_data);
            $this->populate_urls($formatted_data, $raw_data, $scheme);
            $this->populate_filename($formatted_data, $raw_data, $scheme);
            
            return $formatted_data;
            
        } catch (Exception $e) {
            $this->logger->exception($e, 'Error formatting asset data from API');
            return false;
        }
    }
    
    /**
     * Prepare return value based on field configuration
     *
     * @param array|false $asset_data The asset data
     * @param array $field The field configuration
     * @return mixed
     */
    public function prepare_return_value($asset_data, $field)
    {
        if (!$asset_data || !is_array($asset_data)) {
            return false;
        }
        
        $return_format = isset($field['return_format']) ? $field['return_format'] : 'object';
        
        switch ($return_format) {
            case 'id':
                return isset($asset_data['id']) ? $asset_data['id'] : false;
                
            case 'url':
                return isset($asset_data['url']) ? $asset_data['url'] : false;
                
            case 'download_url':
                return isset($asset_data['download_url']) ? $asset_data['download_url'] : false;
                
            case 'object':
            default:
                return $asset_data;
        }
    }
    
    /**
     * Validate asset data structure
     *
     * @param mixed $data
     * @return bool
     */
    private function is_valid_asset_data($data)
    {
        return is_array($data) && isset($data['id']) && !empty($data['id']);
    }
    
    /**
     * Determine asset scheme from data
     *
     * @param array $data
     * @return string
     */
    private function determine_asset_scheme($data)
    {
        // Check explicit scheme field
        if (isset($data['scheme']) && in_array($data['scheme'], array('image', 'video', 'document'))) {
            return $data['scheme'];
        }
        
        // Try to determine from URL structure
        if (isset($data['url']['preview'])) {
            $preview_url = $data['url']['preview'];
            
            if (strpos($preview_url, '/video/') !== false) {
                return 'video';
            } elseif (strpos($preview_url, '/document/') !== false) {
                return 'document';
            }
        }
        
        // Try to determine from metadata
        if (isset($data['default']['Content Type'])) {
            $mime_type = strtolower($data['default']['Content Type']);
            
            if (strpos($mime_type, 'video/') === 0) {
                return 'video';
            } elseif (strpos($mime_type, 'application/') === 0 || strpos($mime_type, 'text/') === 0) {
                return 'document';
            }
        }
        
        // Default to image
        return 'image';
    }
    
    /**
     * Build base asset data structure
     *
     * @param array $data
     * @param string $scheme
     * @param string $asset_id
     * @return array
     */
    private function build_base_asset_data($data, $scheme, $asset_id = null)
    {
        return array(
            'id' => $asset_id ?: $data['id'],
            'scheme' => $scheme,
            'name' => isset($data['name']) ? $data['name'] : __('Untitled', 'acf-canto-field'),
            'filename' => '',
            'url' => '',
            'thumbnail' => '',
            'download_url' => '',
            'dimensions' => '',
            'mime_type' => '',
            'size' => '',
            'uploaded' => isset($data['lastUploaded']) ? $data['lastUploaded'] : '',
            'metadata' => array(),
        );
    }
    
    /**
     * Populate metadata from raw data
     *
     * @param array &$formatted_data
     * @param array $raw_data
     */
    private function populate_metadata(&$formatted_data, $raw_data)
    {
        if (isset($raw_data['default']) && is_array($raw_data['default'])) {
            $formatted_data['metadata'] = $raw_data['default'];
            
            // Extract common metadata fields
            $this->extract_dimensions($formatted_data, $raw_data['default']);
            $this->extract_mime_type($formatted_data, $raw_data['default']);
            $this->extract_file_size($formatted_data, $raw_data);
        }
    }
    
    /**
     * Extract dimensions from metadata
     *
     * @param array &$formatted_data
     * @param array $metadata
     */
    private function extract_dimensions(&$formatted_data, $metadata)
    {
        $dimension_fields = array('Dimensions', 'Size', 'Resolution');
        
        foreach ($dimension_fields as $field) {
            if (isset($metadata[$field]) && !empty($metadata[$field])) {
                $formatted_data['dimensions'] = $metadata[$field];
                break;
            }
        }
    }
    
    /**
     * Extract MIME type from metadata
     *
     * @param array &$formatted_data
     * @param array $metadata
     */
    private function extract_mime_type(&$formatted_data, $metadata)
    {
        $mime_fields = array('Content Type', 'MIME Type', 'Type');
        
        foreach ($mime_fields as $field) {
            if (isset($metadata[$field]) && !empty($metadata[$field])) {
                $formatted_data['mime_type'] = $metadata[$field];
                break;
            }
        }
    }
    
    /**
     * Extract file size from data
     *
     * @param array &$formatted_data
     * @param array $raw_data
     */
    private function extract_file_size(&$formatted_data, $raw_data)
    {
        if (isset($raw_data['size']) && is_numeric($raw_data['size'])) {
            $formatted_data['size'] = size_format($raw_data['size']);
        }
    }
    
    /**
     * Populate URLs from raw data
     *
     * @param array &$formatted_data
     * @param array $raw_data
     * @param string $scheme
     */
    private function populate_urls(&$formatted_data, $raw_data, $scheme)
    {
        // Extract URLs from API response
        if (isset($raw_data['url']) && is_array($raw_data['url'])) {
            $this->extract_api_urls($formatted_data, $raw_data['url']);
        }
        
        // Build fallback URLs
        $this->build_fallback_urls($formatted_data, $scheme);
    }
    
    /**
     * Extract URLs from API response
     *
     * @param array &$formatted_data
     * @param array $url_data
     */
    private function extract_api_urls(&$formatted_data, $url_data)
    {
        // Priority 1: Use directUrlPreview for preview URL (direct access, no auth needed)
        if (isset($url_data['directUrlPreview'])) {
            $formatted_data['url'] = $url_data['directUrlPreview'];
            $formatted_data['thumbnail'] = $url_data['directUrlPreview'];
        }
        // Priority 2: Fall back to API preview if directUrlPreview is not available
        elseif (isset($url_data['preview'])) {
            $formatted_data['url'] = $url_data['preview'];
        }
        
        // Priority 1: Use directUrlOriginal for download URL (the actual file)
        if (isset($url_data['directUrlOriginal'])) {
            $formatted_data['download_url'] = $url_data['directUrlOriginal'];
        }
        // Priority 2: Fall back to download if directUrlOriginal is not available
        elseif (isset($url_data['download'])) {
            $formatted_data['download_url'] = $url_data['download'];
        }
    }
    
    /**
     * Build fallback URLs for missing API URLs
     *
     * @param array &$formatted_data
     * @param string $scheme
     */
    private function build_fallback_urls(&$formatted_data, $scheme)
    {
        $asset_id = $formatted_data['id'];
        
        // Build download URL if not provided
        if (empty($formatted_data['download_url'])) {
            $formatted_data['download_url'] = $this->api->build_download_url($asset_id, $scheme);
        }
        
        // Build thumbnail URL if not provided
        if (empty($formatted_data['thumbnail'])) {
            $formatted_data['thumbnail'] = $this->build_thumbnail_url($asset_id, $scheme);
        }
    }
    
    /**
     * Build thumbnail URL with fallbacks
     *
     * @param string $asset_id
     * @param string $scheme
     * @return string
     */
    private function build_thumbnail_url($asset_id, $scheme)
    {
        // Try proxy URL first
        if ($this->api->is_configured()) {
            return $this->api->build_thumbnail_url($asset_id, $scheme);
        }
        
        // Fallback to default icons
        return $this->get_default_thumbnail($scheme);
    }
    
    /**
     * Get default thumbnail based on scheme
     *
     * @param string $scheme
     * @return string
     */
    private function get_default_thumbnail($scheme)
    {
        $plugin_url = ACF_CANTO_FIELD_PLUGIN_URL;
        
        $thumbnails = array(
            'video' => $plugin_url . 'assets/images/default-video.svg',
            'document' => $plugin_url . 'assets/images/default-document.svg',
            'image' => $plugin_url . 'assets/images/default-image.svg',
        );
        
        return isset($thumbnails[$scheme]) ? $thumbnails[$scheme] : $thumbnails['image'];
    }
    
    /**
     * Populate filename from various sources
     *
     * @param array &$formatted_data
     * @param array $raw_data
     * @param string $scheme
     */
    private function populate_filename(&$formatted_data, $raw_data, $scheme)
    {
        // Try to extract from metadata
        if (isset($raw_data['default']) && is_array($raw_data['default'])) {
            $filename = $this->extract_filename_from_metadata($raw_data['default']);
            if ($filename) {
                $formatted_data['filename'] = $filename;
                return;
            }
        }
        
        // Try to extract from name
        $filename = $this->extract_filename_from_name($formatted_data['name']);
        if ($filename) {
            $formatted_data['filename'] = $filename;
            return;
        }
        
        // Generate filename from name and scheme
        $formatted_data['filename'] = $this->generate_filename($formatted_data['name'], $scheme);
    }
    
    /**
     * Extract filename from metadata
     *
     * @param array $metadata
     * @return string|false
     */
    private function extract_filename_from_metadata($metadata)
    {
        $filename_fields = array('Filename', 'File Name', 'Original Filename', 'filename', 'file_name');
        
        foreach ($filename_fields as $field) {
            if (isset($metadata[$field]) && !empty($metadata[$field])) {
                return $metadata[$field];
            }
        }
        
        return false;
    }
    
    /**
     * Extract filename from name if it has an extension
     *
     * @param string $name
     * @return string|false
     */
    private function extract_filename_from_name($name)
    {
        if (preg_match('/\.[a-zA-Z0-9]{2,5}$/', $name)) {
            return $name;
        }
        
        return false;
    }
    
    /**
     * Generate filename from name and scheme
     *
     * @param string $name
     * @param string $scheme
     * @return string
     */
    private function generate_filename($name, $scheme)
    {
        $extension_map = array(
            'image' => 'jpg',
            'video' => 'mp4',
            'document' => 'pdf'
        );
        
        $extension = isset($extension_map[$scheme]) ? $extension_map[$scheme] : 'bin';
        $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        
        return $safe_name . '.' . $extension;
    }
}