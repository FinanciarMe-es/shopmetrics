<?php

namespace ShopMetrics\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Handles communication with the FinanciarMe backend API.
 *
 * @since      1.1.0
 * @package    ShopMetrics
 * @subpackage ShopMetrics/includes
 * @author     FinanciarMe <info@financiarme.es>
 */
class ShopMetrics_Api_Client {

    /**
     * Sends a request to the FinanciarMe backend API.
     *
     * @since 1.1.0
     * @param string $endpoint_path The API endpoint path (e.g., 'orders', 'track/event').
     * @param array  $payload       Associative array of data for the request body.
     * @param string $method        HTTP method (e.g., 'POST', 'GET', 'PUT'). Defaults to 'POST'.
     * @return array|true|\WP_Error Decoded JSON response as an array, true on success with no body, or WP_Error on failure.
     */
    public static function send_request( $endpoint_path, $payload = array(), $method = 'POST' ) {
        $api_base_url = SHOPMETRICS_API_URL;
        $api_token = get_option( 'shopmetrics_analytics_api_token', '' );
        $site_identifier = get_option( 'shopmetrics_analytics_site_identifier', '' );

        // Debug log the configuration values (redacting part of the token for security)
        $masked_token = !empty($api_token) ? substr($api_token, 0, 6) . '...' . substr($api_token, -4) : 'empty';
        		\ShopMetrics_Logger::get_instance()->debug( "API URL: {$api_base_url}, Site ID: {$site_identifier}, Token: {$masked_token}" );

        if ( empty( $api_base_url ) || empty( $api_token ) || empty( $site_identifier ) ) {
            			\ShopMetrics_Logger::get_instance()->error( 'API settings (URL, Token, or Site ID) are incomplete. Cannot send request.' );
            return new \WP_Error( 'api_config_error', 'API configuration is incomplete.', array( 'status' => 500 ) );
        }

        // Improved URL construction to avoid double slashes
        $api_base_url = rtrim($api_base_url, '/');
        $endpoint_path = ltrim($endpoint_path, '/');
        $request_url = $api_base_url . '/' . $endpoint_path;
        
        $method = strtoupper( $method );
        
        \ShopMetrics_Logger::get_instance()->info("Final request URL: {$request_url}");
        
        // Also log the exact headers being sent for debugging
        \ShopMetrics_Logger::get_instance()->debug("Headers - X-FinanciarMe-Site-Identifier: {$site_identifier}, X-FinanciarMe-Token: {$masked_token}");

        $request_args = array(
            'method'      => $method,
            'timeout'     => apply_filters( 'shopmetrics_analytics_api_timeout', 30 ), // Allow filtering timeout
            'redirection' => apply_filters( 'shopmetrics_analytics_api_redirection', 5 ),
            'httpversion' => '1.1',
            'blocking'    => true, // Wait for the response
            'headers'     => array(
                'Content-Type'                => 'application/json; charset=utf-8',
                'X-FinanciarMe-Token'         => $api_token,
                'X-FinanciarMe-Site-Identifier' => $site_identifier,
                'User-Agent'                  => 'FinanciarMeAnalyticsWordPressPlugin/' . SHOPMETRICS_VERSION
            ),
            'body'        => null, // Will be set for methods that have a body
            'data_format' => 'body',
        );

        // Add body only for methods that typically have one
        if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ) ) && ! empty( $payload ) ) {
            $request_args['body'] = wp_json_encode( $payload );
        }

        \ShopMetrics_Logger::get_instance()->info( "Sending {$method} request to {$request_url} with payload: " . wp_json_encode($payload) );

        // Check specifically for track/event endpoint to ensure correct format
        if (strpos($endpoint_path, 'track/event') !== false && isset($payload['event_data'])) {
            \ShopMetrics_Logger::get_instance()->info( "Detected track/event endpoint. Payload has event_data key." );
        }

        $response = wp_remote_request( $request_url, $request_args );

        if ( is_wp_error( $response ) ) {
            \ShopMetrics_Logger::get_instance()->error( "WP_Error during {$method} request to {$endpoint_path}: " . $response->get_error_message() );
            return $response; // Return the WP_Error object
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        \ShopMetrics_Logger::get_instance()->info( "Response from {$endpoint_path} - Code: {$response_code}, Body: " . $response_body );
        
        // Extra debugging for unauthorized responses
        if ($response_code === 401) {
            $response_headers = wp_remote_retrieve_headers($response);
            \ShopMetrics_Logger::get_instance()->error("Unauthorized (401) response. Headers: " . wp_json_encode($response_headers));
            \ShopMetrics_Logger::get_instance()->error("Request headers sent - Token: {$masked_token}, Site ID: {$site_identifier}");
        }

        if ( $response_code >= 200 && $response_code < 300 ) {
            if ( empty( $response_body ) || $response_code === 204 ) {
                return true; // Success with no content
            }
            $decoded_body = json_decode( $response_body, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                return $decoded_body; // Success with JSON body
            }
            // Successful HTTP code but non-JSON body or JSON decode error
            \ShopMetrics_Logger::get_instance()->info( "Successful HTTP response from {$endpoint_path} but failed to decode JSON body. JSON Error: " . json_last_error_msg() );
            return new \WP_Error('api_response_decode_error', 'Failed to decode JSON response from API.', array( 'status' => $response_code, 'body' => $response_body ) );
        }
        
        // Handle non-2xx HTTP status codes
        $error_message = 'API request failed.';
        $decoded_body = json_decode( $response_body, true );
        if ( $decoded_body && isset( $decoded_body['message'] ) ) {
            $error_message = $decoded_body['message'];
        } elseif ( $decoded_body && isset( $decoded_body['error']['message'] ) ) {
            $error_message = $decoded_body['error']['message'];
        } elseif ( !empty($response_body) ) {
            $error_message = $response_body;
        }

        \ShopMetrics_Logger::get_instance()->error( "Error response from {$endpoint_path} - Code: {$response_code}, Message: {$error_message}" );
        return new \WP_Error( 'api_request_failed', $error_message, array( 'status' => $response_code, 'body' => $response_body ) );
    }
    
    /**
     * Sends a batch of orders to the FinanciarMe backend API using the bulk orders endpoint.
     *
     * @since 1.2.0
     * @param array $orders_data Array of order data objects.
     * @return array|true|\WP_Error Response from the API.
     */
    public static function send_bulk_orders( $orders_data ) {
        // Define the correct v2 endpoint path
        $endpoint_path = 'v2/orders/bulk';
        
        // Get the base API URL without any version prefix
        $api_base_url = SHOPMETRICS_API_URL;
        $api_base_url = preg_replace('#/v[0-9]+/?$#', '', rtrim($api_base_url, '/')); // Remove any version suffix like /v1
        
        \ShopMetrics_Logger::get_instance()->info("Base API URL for bulk orders: {$api_base_url}");
        \ShopMetrics_Logger::get_instance()->info("Using endpoint path: {$endpoint_path}");
        
        // Prepare payload with orders array
        $payload = array(
            'orders' => $orders_data
        );
        
        // Use a longer timeout for bulk operations
        add_filter( 'shopmetrics_analytics_api_timeout', function() { return 60; }, 999 );
        
        // Log the bulk request
        \ShopMetrics_Logger::get_instance()->info( "Sending bulk orders request with " . count($orders_data) . " orders" );
        
        // Construct the full URL properly
        $url = $api_base_url . '/' . ltrim($endpoint_path, '/');
        \ShopMetrics_Logger::get_instance()->info("Full URL for bulk orders: {$url}");
        
        try {
            // Get auth data
            $api_token = get_option( 'shopmetrics_analytics_api_token', '' );
            $site_identifier = get_option( 'shopmetrics_analytics_site_identifier', '' );
            
            // Debug log the configuration values (redacting part of the token for security)
            $masked_token = !empty($api_token) ? substr($api_token, 0, 6) . '...' . substr($api_token, -4) : 'empty';
            \ShopMetrics_Logger::get_instance()->info("Bulk orders: Using Token: {$masked_token}, Site ID: {$site_identifier}");
            
            // Create request
            $request_args = array(
                'method'      => 'POST',
                'timeout'     => 60,
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking'    => true,
                'headers'     => array(
                    'Content-Type'                  => 'application/json; charset=utf-8',
                    'X-FinanciarMe-Token'           => $api_token,
                    'X-FinanciarMe-Site-Identifier' => $site_identifier,
                    'User-Agent'                    => 'FinanciarMeAnalyticsWordPressPlugin/' . SHOPMETRICS_VERSION
                ),
                'body'        => wp_json_encode($payload),
                'data_format' => 'body',
            );
            
            \ShopMetrics_Logger::get_instance()->info("Sending bulk orders to URL: {$url}");
            $response = wp_remote_post($url, $request_args);
            
            if (is_wp_error($response)) {
                \ShopMetrics_Logger::get_instance()->info("Error sending bulk orders: " . $response->get_error_message());
                return $response;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            \ShopMetrics_Logger::get_instance()->info("Bulk orders response: Code {$response_code}, Body: {$response_body}");
            
            // Extra debugging for unauthorized responses
            if ($response_code === 401) {
                $response_headers = wp_remote_retrieve_headers($response);
                $masked_token = !empty($api_token) ? substr($api_token, 0, 6) . '...' . substr($api_token, -4) : 'empty';
                \ShopMetrics_Logger::get_instance()->error("Bulk orders: Unauthorized (401) response. Headers: " . wp_json_encode($response_headers));
                \ShopMetrics_Logger::get_instance()->error("Bulk orders: Request headers sent - Token: {$masked_token}, Site ID: {$site_identifier}");
            }
            
            if ($response_code >= 200 && $response_code < 300) {
                if (empty($response_body) || $response_code === 204) {
                    return true;
                }
                $decoded_body = json_decode($response_body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded_body;
                }
                return new \WP_Error('api_response_decode_error', 'Failed to decode JSON response from API.', array('status' => $response_code, 'body' => $response_body));
            }
            
            // Handle error response
            $error_message = 'API request failed.';
            $decoded_body = json_decode($response_body, true);
            if ($decoded_body && isset($decoded_body['message'])) {
                $error_message = $decoded_body['message'];
            } elseif ($decoded_body && isset($decoded_body['error']['message'])) {
                $error_message = $decoded_body['error']['message'];
            } elseif (!empty($response_body)) {
                $error_message = $response_body;
            }
            
            return new \WP_Error('api_request_failed', $error_message, array('status' => $response_code, 'body' => $response_body));
        } finally {
            // Remove our custom timeout filter
            remove_filter('shopmetrics_analytics_api_timeout', function() { return 60; }, 999);
        }
    }
} 