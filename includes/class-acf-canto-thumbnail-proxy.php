<?php
/**
 * ACF Canto Thumbnail Proxy
 *
 * Resolves ?canto_thumbnail=1&asset_type=TYPE&asset_id=ID to the high-res
 * directUrlPreview from the Canto API and redirects the browser to it.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ACF_Canto_Thumbnail_Proxy
{
    public function __construct()
    {
        // Priority 1 so this runs before SAML/SSO plugins can intercept.
        add_action('init', array($this, 'handle_thumbnail_request'), 1);
    }

    /**
     * Handle thumbnail requests
     */
    public function handle_thumbnail_request()
    {
        if (empty($_GET['canto_thumbnail'])) {
            return;
        }

        $asset_type = isset($_GET['asset_type']) ? sanitize_text_field($_GET['asset_type']) : '';
        $asset_id   = isset($_GET['asset_id']) ? sanitize_text_field($_GET['asset_id']) : '';

        if (empty($asset_type) || empty($asset_id)) {
            status_header(400);
            exit('Bad Request');
        }

        $this->serve_thumbnail($asset_type, $asset_id);
    }

    /**
     * Look up the directUrlPreview from the Canto API and redirect to it.
     */
    private function serve_thumbnail($asset_type, $asset_id)
    {
        $token   = get_option('fbc_app_token');
        $domain  = get_option('fbc_flight_domain');
        $app_api = get_option('fbc_app_api') ?: 'canto.com';

        if (!$token || !$domain) {
            status_header(404);
            exit('Not Found');
        }

        // Ask the Canto metadata API for the asset's URLs
        $api_url = 'https://' . $domain . '.' . $app_api . '/api/v1/' . $asset_type . '/' . $asset_id;

        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json;charset=utf-8',
            ),
            'timeout' => 30,
        ));

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);

            if (!empty($data['url']['directUrlPreview'])) {
                // Clean any output that may have been sent by other plugins
                while (ob_get_level()) {
                    ob_end_clean();
                }

                header('Location: ' . $data['url']['directUrlPreview'], true, 302);
                header('Cache-Control: public, max-age=3600');
                exit;
            }
        }

        status_header(404);
        exit('Not Found');
    }
}

new ACF_Canto_Thumbnail_Proxy();
