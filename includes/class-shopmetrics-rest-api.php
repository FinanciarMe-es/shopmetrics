<?php

namespace ShopMetrics\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Handles the REST API endpoints for ShopMetrics.
 *
 * @since      1.0.0
 * @package    Financiarme_Analytics
 * @subpackage Financiarme_Analytics/includes
 * @author     FinanciarMe <info@financiarme.es>
 */
class ShopMetrics_Rest_Api {

    /**
     * The namespace for the REST API.
     *
     * @var string
     */
    protected $namespace = 'shopmetrics/v1';

    /**
	 * Initialize hooks.
	 *
	 * @since    1.0.0
	 */
    public function init_hooks() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
	 * Register the REST API routes.
	 *
	 * @since    1.0.0
	 */
    public function register_routes() {
        register_rest_route( $this->namespace, '/sync-history', array(
            'methods'             => \WP_REST_Server::CREATABLE, // Use POST for triggering actions
            'callback'            => array( $this, 'handle_sync_history_request' ),
            'permission_callback' => array( $this, 'sync_history_permissions_check' ),
            // 'args' => array( // Optional: Define expected request arguments later
            // 	'force_resync' => array(
            // 		'description'       => 'Whether to force a full resynchronization.',
            // 		'type'              => 'boolean',
            // 		'default'           => false,
            // 		'sanitize_callback' => 'rest_sanitize_boolean',
            // 	),
            // ),
        ) );

        register_rest_route( $this->namespace, '/initiate_connection', array(
            'methods'             => \WP_REST_Server::CREATABLE, // POST
            'callback'            => array( $this, 'handle_initiate_connection' ),
            'permission_callback' => array( $this, 'initiate_connection_permissions_check' ),
            'args'                => array(
                'site_identifier' => array(
                    'description'       => 'The URL of the WordPress site initiating the connection.',
                    'type'              => 'string',
                    'format'            => 'uri',
                    'required'          => true,
                    'sanitize_callback' => 'esc_url_raw',
                ),
            ),
        ) );

        // TODO: Add endpoint for /save-token
        register_rest_route( $this->namespace, '/save_token', array(
            'methods'             => \WP_REST_Server::CREATABLE, // POST
            'callback'            => array( $this, 'handle_save_token' ),
            'permission_callback' => array( $this, 'save_token_permissions_check' ),
            'args'                => array(
                'api_token' => array(
                    'description'       => 'The permanent API token issued by the backend.',
                    'type'              => 'string',
                    'required'          => true,
                    'validate_callback' => function( $param, $request, $key ) {
                        // Basic validation: Ensure it's a non-empty string
                        return is_string( $param ) && ! empty( trim( $param ) );
                    },
                    // No sanitize callback here - we store the exact token provided
                ),
            ),
        ) );

        // TODO: Add other endpoints as needed (e.g., get dashboard data, get sync status)
    }

    /**
     * Permission check for the sync history endpoint.
     *
     * Only allow users who can manage options (administrators) to trigger sync.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full details about the request.
     * @return bool True if the user has permission, otherwise false.
     */
    public function sync_history_permissions_check( $request ) {
        // Use 'manage_woocommerce' capability check if you want store managers to sync
        // return current_user_can( 'manage_woocommerce' ); 
        return current_user_can( 'manage_options' ); 
    }

    /**
     * Handle the request to trigger a historical data sync.
     *
     * Schedules a background task using Action Scheduler.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
     */
    public function handle_sync_history_request( $request ) {
        // Check if Action Scheduler is available
        if ( ! function_exists( 'as_enqueue_async_action' ) ) {
             \ShopMetrics_Logger::get_instance()->info("Action Scheduler not found. Cannot schedule sync.");
            return new \WP_Error(
                'action_scheduler_missing',
                __( 'Action Scheduler library is required but not found. Please ensure WooCommerce is active.', 'shopmetrics' ),
                array( 'status' => 500 )
            );
        }
        
        // Define the hook for our async action
        $action_hook = 'shopmetrics_analytics_do_historical_sync';
        
        // Check if an action is already scheduled or running to prevent duplicates
        // (Check for pending and in-progress actions)
        $scheduled_actions = as_get_scheduled_actions( array(
            'hook' => $action_hook,
            'status' => array( \ActionScheduler_Store::STATUS_PENDING, \ActionScheduler_Store::STATUS_RUNNING ),
            'per_page' => 1, // We only need to know if at least one exists
        ), 'ids' );
        
        if ( ! empty( $scheduled_actions ) ) {
             return new \WP_REST_Response( array(
                'success' => true,
                'message' => __( 'Synchronization is already in progress or scheduled.', 'shopmetrics' )
            ), 202 ); // 202 Accepted: request accepted, but processing not complete
        }
        
        // Schedule the async action (runs ASAP)
        $action_id = as_enqueue_async_action( $action_hook, array(), 'shopmetrics-sync' ); // group name
        
        if ( ! $action_id ) {
            \ShopMetrics_Logger::get_instance()->error("Failed to schedule historical sync action.");
            return new \WP_Error(
                'scheduling_failed',
                __( 'Failed to schedule the synchronization task.', 'shopmetrics' ),
                array( 'status' => 500 )
            );
        }
        
        // Return a success response
        return new \WP_REST_Response( array(
            'success' => true,
            'message' => __( 'Historical data synchronization has been scheduled.', 'shopmetrics' ),
            'action_id' => $action_id
        ), 200 ); 
    }
    
    // TODO: Define the actual sync function that Action Scheduler will call. 
    // This function should be hooked to 'shopmetrics_analytics_do_historical_sync'

    /**
     * Permission check for the initiate connection endpoint.
     *
     * Only allow users who can manage options (administrators) to initiate.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full details about the request.
     * @return bool True if the user has permission, otherwise false.
     */
    public function initiate_connection_permissions_check( $request ) {
        // Nonce is checked automatically by WP REST Server for authenticated requests with _wpnonce
        return current_user_can( 'manage_options' ); 
    }

    /**
     * Handles the request to initiate the site connection process.
     *
     * Generates a verification code, triggers a callback to the site, and returns a permanent token upon success.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
     */
    public function handle_initiate_connection( $request ) {
        $site_identifier = $request->get_param('site_identifier');
        $verification_code = wp_generate_password( 32, false ); // Generate a strong random code
        $transient_key = 'sm_verify_' . $verification_code;
        $success_string = 'FINANCIARME_VERIFIED_OK';

        // Store the code with the site URL for verification, short expiry
        set_transient( $transient_key, $site_identifier, 5 * MINUTE_IN_SECONDS );

        // Construct the verification URL
        $verification_url = add_query_arg( 'shopmetrics_verify', $verification_code, $site_identifier );

        // Make the callback request
        $verify_response = wp_remote_get( $verification_url, array(
            'timeout' => 10, // Short timeout for verification
            'sslverify' => apply_filters( 'https_local_ssl_verify', false ) // Allow self-signed certs for local dev often needed
        ) );

        // Check the response
        if ( is_wp_error( $verify_response ) ) {
            delete_transient( $transient_key );
            \ShopMetrics_Logger::get_instance()->error("Verification callback failed for $site_identifier. Error: " . $verify_response->get_error_message());
            return new \WP_Error(
                'verification_callback_failed',
                __( 'Could not reach your site for verification. Please ensure your site is publicly accessible or check firewall settings.', 'shopmetrics' ),
                array( 'status' => 400, 'details' => $verify_response->get_error_message() )
            );
        }

        $verify_body = wp_remote_retrieve_body( $verify_response );
        $verify_code = wp_remote_retrieve_response_code( $verify_response );

        // Verify the success string
        if ( $verify_code !== 200 || trim( $verify_body ) !== $success_string ) {
            delete_transient( $transient_key );
            \ShopMetrics_Logger::get_instance()->error("Verification challenge failed for $site_identifier. Response Code: $verify_code, Body: $verify_body");
             return new \WP_Error(
                'verification_challenge_failed',
                __( 'Site verification challenge failed. Unexpected response received.', 'shopmetrics' ),
                array( 'status' => 400 )
            );
        }

        // Verification successful! 
        delete_transient( $transient_key ); // Clean up transient
        
        // --- Register Site with Backend --- 
        $plaintext_token = 'sm_perm_' . wp_generate_password( 64, false );
        $hashed_token = hash( 'sha256', $plaintext_token );
        $registration_url = SHOPMETRICS_API_URL . '/v1/register_site'; // Use constant for API base URL
        $site_identifier_to_register = $request->get_param('site_identifier'); // Get it again for clarity

        $registration_args = array(
            'method'      => 'POST',
            'timeout'     => 15,
            'headers'     => array(
                'Content-Type' => 'application/json',
                // 'x-api-key'    => 'YOUR_MANUALLY_CREATED_API_KEY_VALUE' // No longer needed for register_site
            ),
            'body'        => json_encode( array(
                'site_identifier' => $site_identifier_to_register,
                'hashed_token'    => $hashed_token,
                'status'          => 'active'  // Site connection status (active/inactive)
                // subscription_status is determined by backend based on existing records
            ) ),
            'data_format' => 'body',
            'sslverify'   => !defined('WP_ENVIRONMENT_TYPE') || WP_ENVIRONMENT_TYPE === 'production', // Allow self-signed in dev/local
        );

        \ShopMetrics_Logger::get_instance()->info("Attempting to register site $site_identifier_to_register with backend.");
        $registration_response = wp_remote_post( $registration_url, $registration_args );

        if ( is_wp_error( $registration_response ) ) {
            $error_message = $registration_response->get_error_message();
            \ShopMetrics_Logger::get_instance()->error("Backend registration failed for $site_identifier_to_register. Error: $error_message");
            return new \WP_Error(
                'backend_registration_failed',
                __( 'Could not register site with the backend service. Please try again.', 'shopmetrics' ),
                array( 'status' => 500, 'details' => $error_message )
            );
        }

        $reg_response_code = wp_remote_retrieve_response_code( $registration_response );
        $reg_response_body = wp_remote_retrieve_body( $registration_response );

        if ( $reg_response_code >= 300 ) {
             \ShopMetrics_Logger::get_instance()->error("Backend registration returned error for $site_identifier_to_register. Code: $reg_response_code, Body: $reg_response_body");
             // Try to parse the error message from the backend response
             $error_data = json_decode($reg_response_body, true);
             $backend_message = isset($error_data['message']) ? $error_data['message'] : __( 'Unknown error from backend registration.', 'shopmetrics' );
             return new \WP_Error(
                'backend_registration_error',
                // translators: %s is the error message from the backend
                sprintf( __( 'Backend registration failed: %s', 'shopmetrics' ), $backend_message ),
                array( 'status' => $reg_response_code )
            );
        }
        
        // --- Registration successful, return PLAINTEXT token to React app --- 
        \ShopMetrics_Logger::get_instance()->info("Site verification and backend registration successful for $site_identifier_to_register. Issuing token.");
        
        return new \WP_REST_Response( array(
            'success' => true,
            'data' => array(
                'api_token' => $plaintext_token,
                'message' => __( 'Site connected successfully. Securely saving token...', 'shopmetrics' )
             )
        ), 200 );
    }

    /**
     * Permission check for the save token endpoint.
     *
     * Only allow users who can manage options (administrators) to save the token.
     * 
     * @since 1.0.0
     * @param \WP_REST_Request $request Full details about the request.
     * @return bool True if the user has permission, otherwise false.
     */
    public function save_token_permissions_check( $request ) {
        // Nonce is checked automatically by WP REST Server for authenticated requests with _wpnonce
        return current_user_can( 'manage_options' ); 
    }

    /**
     * Handles the request to save the permanent API token.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
     */
    public function handle_save_token( $request ) {
        $api_token = $request->get_param('api_token');
        $option_name = 'shopmetrics_analytics_api_token';

        // Use update_option - it handles creating or updating the option.
        // It also returns true on success or if the value is unchanged, false on failure.
        $saved = update_option( $option_name, $api_token, false ); // false = don't autoload

        if ( $saved ) {
            \ShopMetrics_Logger::get_instance()->info("API Token saved successfully.");
            return new \WP_REST_Response( array(
                'success' => true,
                'message' => __( 'API Token saved successfully.', 'shopmetrics' )
            ), 200 );
        } else {
            \ShopMetrics_Logger::get_instance()->error("Failed to save API Token to options table.");
            return new \WP_Error(
                'token_save_failed',
                __( 'Could not save the API token to the database.', 'shopmetrics' ),
                array( 'status' => 500 )
            );
        }
    }

    // TODO: Add handle_save_token method

} 