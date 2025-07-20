<?php

namespace ShopMetrics\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Handles cart and checkout process tracking for ShopMetrics.
 *
 * @since      1.1.0
 * @package    ShopMetrics
 * @subpackage ShopMetrics/includes
 * @author     FinanciarMe <info@financiarme.es>
 */
class ShopMetrics_Cart_Tracker {

    /**
     * Reference to the main plugin class instance.
     * @var ShopMetrics
     */
    protected $plugin;

    const ACTIVE_CART_OPTION_PREFIX = 'shopmetrics_active_cart_';
    const EMAIL_SENT_OPTION_PREFIX = 'shopmetrics_cart_recovery_email_sent_';
    const ABANDONMENT_CRON_HOOK = 'shopmetrics_check_abandoned_carts_hook';
    const DEBOUNCED_CART_UPDATE_HOOK = 'shopmetrics_process_debounced_cart_update_hook';
    const ABANDONMENT_THRESHOLD_HOURS = 4;
    // const USER_CART_TRANSIENT_PREFIX = 'sm_user_cart_tracker_'; // No longer using transients this way

    private static $abandoned_cron_scheduled_this_request = false;
    private static $debounced_hook_suffix_counter = 0; // To ensure unique hook names for concurrent users

    /**
     * Constructor.
     * @param \ShopMetrics\Analytics\ShopMetrics $plugin Main plugin instance.
     */
    public function __construct( \ShopMetrics\Analytics\ShopMetrics $plugin ) {
        $this->plugin = $plugin;
    }

    /**
     * Initialize hooks for cart tracking.
     *
     * @since 1.1.0
     */
    public function init_hooks() {
        // CART EVENTS - These trigger cart_updated events via debounced update
        add_action( 'woocommerce_cart_updated', array( $this, 'handle_cart_activity' ), 20 );
        add_action( 'woocommerce_cart_item_removed', array( $this, 'handle_cart_activity' ), 20 );
        add_action( 'woocommerce_cart_item_restored', array( $this, 'handle_cart_activity' ), 20 );
        add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'handle_cart_activity' ), 20, 4 );
        add_action( 'woocommerce_add_to_cart', array( $this, 'handle_cart_activity' ), 20 );

        // CART PAGE VIEW - This triggers viewed_cart_page events when user actually views the cart page
        add_action( 'woocommerce_before_cart', array( $this, 'handle_view_cart_page' ), 10 );

        // CHECKOUT EVENTS - These trigger checkout_started events only on actual checkout pages
        // Note: template_redirect is the most reliable hook for detecting the checkout page
        add_action( 'template_redirect', array( $this, 'check_for_checkout_page' ), 10 );
        
        // These additional checkout hooks are for backup detection only
        // They may fire during certain theme setups where template_redirect doesn't catch checkout
        add_action( 'woocommerce_before_checkout_form', array( $this, 'handle_checkout_start' ), 10 );
        add_action( 'woocommerce_checkout_init', array( $this, 'handle_checkout_start' ), 10 );
        
        // Cron Job Hook for Abandoned Carts
        add_action( self::ABANDONMENT_CRON_HOOK, array( $this, 'check_abandoned_carts' ) );

        // Hook for the debounced cart update processing - now expects cart_data_json as an arg
        add_action( self::DEBOUNCED_CART_UPDATE_HOOK, array( $this, 'process_debounced_cart_update' ), 10, 3 ); // Increased to 3 args
    }

    /**
     * Schedules the abandoned cart checker cron job.
     *
     * @since 1.1.0
     */
    public function schedule_abandoned_cart_check_cron() {
        if ( self::$abandoned_cron_scheduled_this_request ) {
            return;
        }
        self::$abandoned_cron_scheduled_this_request = true;

        if ( ! wp_next_scheduled( self::ABANDONMENT_CRON_HOOK ) ) {
            wp_schedule_event( time(), 'fifteen_minutes', self::ABANDONMENT_CRON_HOOK );
            \ShopMetrics_Logger::get_instance()->info( "CartTracker: Scheduled abandoned cart check cron ('" . self::ABANDONMENT_CRON_HOOK . "')." );
        }
    }

    /**
     * Retrieves current cart data for tracking.
     * (Largely unchanged, added logging for cart hash generation point)
     * @since 1.1.0
     * @return array|null
     */
    private function get_current_cart_data_for_tracking() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return null;
        }

        $cart = WC()->cart;
        
        // Ensure WooCommerce session is initialized if not already
        if (WC()->session && !WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
                            \ShopMetrics_Logger::get_instance()->debug("CartTracker (get_current_cart_data): Initialized WooCommerce session");
        }
        
        // Get user ID and session ID
        $user_id = get_current_user_id();
        $session_id = WC()->session ? WC()->session->get_customer_id() : null;
        
        // If session_id is still empty, try to create one
        if (empty($session_id)) {
            $session_id = $this->get_or_create_session_id();
                            \ShopMetrics_Logger::get_instance()->debug("CartTracker (get_current_cart_data): Created new session ID: {$session_id}");
        }
        
        // It's crucial to get cart hash *after* potential modifications by hooks that fire before this function.
        // However, if the cart is empty, get_cart_hash() might not be what we expect.
        $current_cart_hash_in_getter = $cart->get_cart_hash();
        // \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartTracker (get_current_cart_data): WC()->cart->get_cart_hash() = " . $current_cart_hash_in_getter);


        if ( $cart->is_empty() ) {
             // If cart is empty, we still want to potentially clear an old active cart option
            return array(
                'items'          => array(),
                'cart_hash'      => $current_cart_hash_in_getter, // Could be a hash representing an empty cart
                'total_value'    => 0,
                'total_items'    => 0,
                'user_id'        => $user_id,
                'session_id'     => $session_id,
                'currency'       => get_woocommerce_currency(),
                'is_empty'       => true
            );
        }

        $cart_items_data = array();
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
            $product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
            
            $line_total_val = 0;
            $line_subtotal_val = 0;

            if ( $_product && $_product->exists() ) {
                if ( isset( $cart_item['line_total'] ) ) {
                    $line_total_val = $cart_item['line_total'];
                } else {
                    $line_total_val = $_product->get_price() * $cart_item['quantity'];
                }
                if ( isset( $cart_item['line_subtotal'] ) ) {
                    $line_subtotal_val = $cart_item['line_subtotal'];
                } else {
                    $line_subtotal_val = $_product->get_price() * $cart_item['quantity'];
                }
            }
            
            $cart_items_data[] = array(
                'product_id'    => $product_id,
                'variation_id'  => $cart_item['variation_id'],
                'name'          => $_product ? $_product->get_name() : '',
                'quantity'      => $cart_item['quantity'],
                'price'         => $_product ? wc_format_decimal($_product->get_price(), wc_get_price_decimals()) : 0,
                'line_total'    => wc_format_decimal($line_total_val, wc_get_price_decimals()),
                'line_subtotal' => wc_format_decimal($line_subtotal_val, wc_get_price_decimals()),
            );
        }
        
        // Re-fetch hash here to ensure it's the absolute latest after item processing, though usually same as above.
        // $final_cart_hash = $cart->get_cart_hash();
        // if ($current_cart_hash_in_getter !== $final_cart_hash) {
        //    \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartTracker (get_current_cart_data): Hash mismatch! Initial: {$current_cart_hash_in_getter}, Final: {$final_cart_hash}");
        // }

        \ShopMetrics_Logger::get_instance()->debug("CartTracker (get_current_cart_data): Retrieved cart with user_id: {$user_id}, session_id: {$session_id}, hash: {$current_cart_hash_in_getter}");

        return array(
            'items'          => $cart_items_data,
            'cart_hash'      => $current_cart_hash_in_getter, // Use the hash obtained at the start of this function
            'total_value'    => wc_format_decimal($cart->get_cart_contents_total(), wc_get_price_decimals()),
            'total_items'    => $cart->get_cart_contents_count(),
            'user_id'        => $user_id,
            'session_id'     => $session_id,
            'currency'       => get_woocommerce_currency(),
            'is_empty'       => false
        );
    }

    /**
     * Sends cart event data to the backend.
     *
     * @since 1.1.0
     * @param string $event_type
     * @param array  $event_specific_data
     */
    private function send_cart_event( $event_type, $event_specific_data ) {
        $site_identifier = get_option( 'shopmetrics_analytics_site_identifier', '' );
        if ( empty( $site_identifier ) ) {
            \ShopMetrics_Logger::get_instance()->warn( "CartTracker: Site Identifier not set. Cannot send '{$event_type}'." );
            return;
        }
        
        // Access our global flag
        global $shopmetrics_cart_update_in_progress;
        
        // Check if this is a checkout_started event during a cart operation
        if ($event_type === 'checkout_started' && 
            ($shopmetrics_cart_update_in_progress === true || get_transient('shopmetrics_cart_update_in_progress'))) {
            \ShopMetrics_Logger::get_instance()->info("Overriding checkout_started event to cart_updated during cart operation");
            $event_type = 'cart_updated';
        }

        // Ensure event_specific_data always has user_id and session_id
        if (!isset($event_specific_data['user_id']) || empty($event_specific_data['user_id'])) {
            $event_specific_data['user_id'] = get_current_user_id();
        }
        
        if (!isset($event_specific_data['session_id']) || empty($event_specific_data['session_id'])) {
            $event_specific_data['session_id'] = $this->get_or_create_session_id();
        }

        // Make sure cart hash is included 
        if (!isset($event_specific_data['cart_hash']) && isset($event_specific_data['items'])) {
            // We have full cart data but no explicit hash
            $event_specific_data['cart_hash'] = !empty($event_specific_data['items']) ? 
                md5(wp_json_encode($event_specific_data['items'])) : '';
        }

        // Add common event fields here
        $payload = array(
            'site_identifier' => $site_identifier,
            'event_type'      => $event_type, // This will be part of the endpoint path now
            'timestamp'       => current_time( 'mysql', true ), // UTC timestamp for the event itself
            'event_data'      => $event_specific_data, 
        );
        
        \ShopMetrics_Logger::get_instance()->debug("About to send event '{$event_type}' with payload: " . wp_json_encode($payload));
        
        $response = ShopMetrics_Api_Client::send_request( 'v1/track/event', $payload, 'POST' );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            $error_code = $response->get_error_code();
            \ShopMetrics_Logger::get_instance()->error("Error sending '{$event_type}' event. Code: {$error_code}, Message: {$error_message}");
            
            // Get detailed error data if available
            $error_data = $response->get_error_data();
            if ( $error_data ) {
                \ShopMetrics_Logger::get_instance()->error("Additional error data: " . wp_json_encode($error_data) );
            }
        } else {
            $log_message = "FinanciarMe CartTracker: Sent '{$event_type}' event.";
            if (is_array($response) || is_object($response)) { // $response is decoded body or true
                $log_message .= " Response: " . wp_json_encode($response);
            }
            \ShopMetrics_Logger::get_instance()->info($log_message);
        }
    }

    /**
     * Generates a unique cart option key based on user_id and session_id
     * 
     * @param int|string $user_id The user ID (0 for guests)
     * @param string $session_id The WooCommerce session ID
     * @return string The option key to use for storing cart data
     */
    private function get_cart_option_key($user_id, $session_id) {
        // For logged-in users, prioritize user_id
        if (!empty($user_id) && $user_id > 0) {
            return self::ACTIVE_CART_OPTION_PREFIX . 'user_' . $user_id;
        }
        // For guests, use session_id
        elseif (!empty($session_id)) {
            return self::ACTIVE_CART_OPTION_PREFIX . 'session_' . $session_id;
        }
        // Fallback (should rarely happen)
        else {
            $unique_id = uniqid('unknown_', true);
            \ShopMetrics_Logger::get_instance()->info("Could not determine user_id or session_id, using generated ID: " . $unique_id);
            return self::ACTIVE_CART_OPTION_PREFIX . $unique_id;
        }
    }

    /**
     * Generates a unique email sent tracking option key based on user_id and session_id
     * 
     * @param int|string $user_id The user ID (0 for guests)
     * @param string $session_id The WooCommerce session ID
     * @return string The option key to use for tracking sent emails
     */
    private function get_email_sent_option_key($user_id, $session_id) {
        // For logged-in users, prioritize user_id
        if (!empty($user_id) && $user_id > 0) {
            return self::EMAIL_SENT_OPTION_PREFIX . 'user_' . $user_id;
        }
        // For guests, use session_id
        elseif (!empty($session_id)) {
            return self::EMAIL_SENT_OPTION_PREFIX . 'session_' . $session_id;
        }
        // Fallback (should rarely happen)
        else {
            $unique_id = uniqid('unknown_', true);
            \ShopMetrics_Logger::get_instance()->info("Could not determine user_id or session_id for email tracking, using generated ID: " . $unique_id);
            return self::EMAIL_SENT_OPTION_PREFIX . $unique_id;
        }
    }

    /**
     * Handles general cart activity.
     *
     * SECURITY NOTE:
     * Nonce verification is NOT used here because this method only checks for WooCommerce action parameters
     * (e.g., wc-ajax, add_to_cart) to control plugin logic and avoid duplicate event tracking.
     * No user input is trusted or processed, and no privileged actions or data modifications are performed.
     */
    public function handle_cart_activity() {
        if ( ( defined('DOING_AJAX') && DOING_AJAX ) || is_admin() ) {
            if (is_admin() && !wp_doing_ajax()){ 
                 return;
            }
            
            // Set a flag during AJAX cart operations to prevent implicit cart views
            if (wp_doing_ajax() && isset($_REQUEST['wc-ajax']) && 
                in_array(sanitize_text_field(wp_unslash($_REQUEST['wc-ajax'])), ['add_to_cart', 'remove_from_cart', 'update_item_quantity'])) {
                set_transient('shopmetrics_skip_implicit_cart_views', true, 10); // Valid for 10 seconds
                \ShopMetrics_Logger::get_instance()->info("Set flag to skip implicit cart views during AJAX operation");
            }
        }

        // Mark that we're performing a cart operation - set this as a global flag as well for immediate access
        global $shopmetrics_cart_update_in_progress;
        $shopmetrics_cart_update_in_progress = true;
        set_transient('shopmetrics_cart_update_in_progress', true, 60); // Valid for 60 seconds
                    \ShopMetrics_Logger::get_instance()->debug("CartTracker (handle_cart_activity): Marked cart update in progress");
        
        // Ensure WooCommerce session is initialized
        if (function_exists('WC') && WC()->session && !WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
            \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartTracker (handle_cart_activity): Initialized WooCommerce session");
        }

        $current_cart_snapshot = $this->get_current_cart_data_for_tracking();

        if ( !$current_cart_snapshot ) {
            \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartTracker (handle_cart_activity): Could not get current cart snapshot. Aborting debounce schedule.");
            return;
        }

        // Ensure user_id and session_id are set correctly
        $user_id = $current_cart_snapshot['user_id'];
        $session_id = $current_cart_snapshot['session_id'];

        // If session_id is still empty, try to create one
        if (empty($session_id)) {
            $session_id = $this->get_or_create_session_id();
            $current_cart_snapshot['session_id'] = $session_id;
            \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartTracker (handle_cart_activity): Created new session ID: {$session_id}");
        }
        
        // Log validation
                    \ShopMetrics_Logger::get_instance()->debug("CartTracker (handle_cart_activity): User ID: {$user_id}, Session ID: {$session_id}");
        
        $cart_data_json = wp_json_encode( $current_cart_snapshot );
        if ( false === $cart_data_json ) {
            \ShopMetrics_Logger::get_instance()->error("FinanciarMe CartTracker (handle_cart_activity): Failed to JSON encode cart snapshot. Aborting debounce schedule.");
            return;
        }
        
        // --- Refined Clearing Logic --- 
        // We want to ensure only one debounced update is scheduled per user/session combination.
        // To do this, we need to clear any existing hooks for THIS specific user/session.
        // wp_clear_scheduled_hook takes the hook name and optionally an $args array.
        // If $args are provided, it only unschedules events matching those exact args.
        // This means we need to know the *exact* $args of a previously scheduled event to clear it this way.
        // A simpler, more aggressive approach is to iterate through all scheduled instances of our hook
        // and unschedule the one matching our user_id and session_id.

        $scheduled_events = _get_cron_array();
        if ( !empty($scheduled_events) ) {
            foreach ( $scheduled_events as $timestamp => $cron_hooks ) {
                if ( isset( $cron_hooks[self::DEBOUNCED_CART_UPDATE_HOOK] ) ) {
                    foreach ( $cron_hooks[self::DEBOUNCED_CART_UPDATE_HOOK] as $key => $event_args_array ) {
                        // $event_args_array['args'] is the array of arguments passed to wp_schedule_single_event
                        if ( isset($event_args_array['args'][0]) && $event_args_array['args'][0] == $user_id && 
                             isset($event_args_array['args'][1]) && $event_args_array['args'][1] == $session_id ) {
                            wp_unschedule_event( $timestamp, self::DEBOUNCED_CART_UPDATE_HOOK, $event_args_array['args'] );
                            \ShopMetrics_Logger::get_instance()->debug("CartTracker (handle_cart_activity): Cleared previously scheduled debounced update for user {$user_id}, session {$session_id} at timestamp {$timestamp}.");
                        }
                    }
                }
            }
        }
        // --- End Refined Clearing Logic ---
        
        $args_for_cron = array( 
            $user_id, 
            $session_id, 
            $cart_data_json 
        );

        $scheduled_time = time() + 7; // Increased delay slightly to allow WC hooks to fully settle
        wp_schedule_single_event( $scheduled_time, self::DEBOUNCED_CART_UPDATE_HOOK, $args_for_cron );
        
        \ShopMetrics_Logger::get_instance()->debug("CartTracker (handle_cart_activity): Debounced cart update scheduled for user {$user_id}, session {$session_id}. Will run around " . gmdate('Y-m-d H:i:s', $scheduled_time));
    }

    /**
     * Handles user viewing the cart page.
     * Only tracks explicit cart page views.
     *
     * SECURITY NOTE:
     * Nonce verification is NOT used here because this method only checks for WooCommerce action parameters
     * to control plugin logic and avoid duplicate event tracking. No user input is trusted or processed.
     */
    public function handle_view_cart_page() {
        // Skip if in admin or during AJAX operations
        if (is_admin() || (wp_doing_ajax() && isset($_REQUEST['wc-ajax']))) {
            return;
        }

        $cart_data = $this->get_current_cart_data_for_tracking();
        if ($cart_data && !$cart_data['is_empty']) { // Only proceed if cart is not empty
            $user_id = $cart_data['user_id'];
            $session_id = $cart_data['session_id'];
            
            // Use a flag to prevent duplicate view events for the same cart
            $viewed_cart_flag_key = 'shopmetrics_viewed_cart_' . md5($user_id . $session_id . $cart_data['cart_hash']);
            
            // Only send the event if we haven't already tracked this cart view
            if (!get_transient($viewed_cart_flag_key)) {
                \ShopMetrics_Logger::get_instance()->info("User viewed cart page. User ID: {$user_id}, Session ID: {$session_id}");
                
                // Get option key based on user_id/session_id
                $option_key = $this->get_cart_option_key($user_id, $session_id);
                
                // Update active cart option timestamp
                $option_value = array('timestamp' => time(), 'data' => $cart_data);
                update_option($option_key, $option_value, 'no');
                
                // Add the 'explicit' flag to the full cart data
                $cart_data['implicit'] = false; // This is an explicit view
                
                $this->send_cart_event('viewed_cart_page', $cart_data);
                
                // Set the transient to prevent duplicate events
                set_transient($viewed_cart_flag_key, true, 3600); // Valid for 1 hour
            } else {
                \ShopMetrics_Logger::get_instance()->warn("Skipping duplicate cart view event - already tracked for this cart hash");
            }
        }
    }

    /**
     * Handles user starting the checkout process.
     * Now also tracks implicit cart view if user hasn't explicitly viewed the cart page.
     *
     * SECURITY NOTE:
     * Nonce verification is NOT used here because this method only checks for WooCommerce action parameters
     * to control plugin logic and avoid duplicate event tracking. No user input is trusted or processed.
     */
    public function handle_checkout_start() {
        if ( is_admin() ) return;
        
        // Skip during AJAX cart operations
        if (get_transient('shopmetrics_skip_implicit_cart_views')) {
            \ShopMetrics_Logger::get_instance()->warn("Skipping checkout_started during AJAX cart operation");
            return;
        }

        \ShopMetrics_Logger::get_instance()->info("handle_checkout_start triggered!");
        
        $cart_data = $this->get_current_cart_data_for_tracking();
        if ( $cart_data && !$cart_data['is_empty'] ) {
            $user_id = $cart_data['user_id'];
            $session_id = $cart_data['session_id'];
            
            // Use unique key for this checkout session to prevent duplicate events
            $checkout_flag_key = 'shopmetrics_checkout_started_' . md5($user_id . $session_id . $cart_data['cart_hash']);
            
            // Check if we've already tracked this checkout event recently (within 30 seconds)
            if ( get_transient( $checkout_flag_key ) ) {
                \ShopMetrics_Logger::get_instance()->warn("Skipping duplicate checkout_started event - already tracked within debounce period");
                return;
            }
            
            // Get option key based on user_id/session_id
            $option_key = $this->get_cart_option_key($user_id, $session_id);
            
            \ShopMetrics_Logger::get_instance()->info("User started checkout. User ID: {$user_id}, Session ID: {$session_id}");

            // Update active cart option timestamp
            $option_value = array( 'timestamp' => time(), 'data' => $cart_data );
            update_option( $option_key, $option_value, 'no' );
            
            // Send the full cart data with the checkout_started event
            \ShopMetrics_Logger::get_instance()->info("About to send checkout_started event with full cart data");
            $this->send_cart_event( 'checkout_started', $cart_data );
            \ShopMetrics_Logger::get_instance()->info("checkout_started event sent");
            
            // Set a transient to prevent duplicate events (valid for 30 seconds)
            set_transient( $checkout_flag_key, true, 30 );
        } else {
            \ShopMetrics_Logger::get_instance()->info("Cannot send checkout_started event - cart is empty or invalid");
        }
    }

    /**
     * Check if the current page is checkout and trigger the checkout start event
     *
     * SECURITY NOTE:
     * Nonce verification is NOT used here because this method only checks for WooCommerce action parameters
     * to control plugin logic and avoid duplicate event tracking. No user input is trusted or processed.
     */
    public function check_for_checkout_page() {
        // Access our global flag
        global $shopmetrics_cart_update_in_progress;
        
        // Don't process during any AJAX requests
        if (wp_doing_ajax()) {
            return;
        }
        
        // Skip if this is a cart-related action  
        if (isset($_GET['add-to-cart']) || isset($_GET['remove_item']) || isset($_GET['undo_item']) || isset($_GET['update_cart'])) {
            \ShopMetrics_Logger::get_instance()->warn("Skipping checkout detection for cart operation via URL parameters");
            return;
        }
        
        // Skip if a cart update is in progress (check both global and transient)
        if ($shopmetrics_cart_update_in_progress === true || get_transient('shopmetrics_cart_update_in_progress')) {
            \ShopMetrics_Logger::get_instance()->warn("Skipping checkout detection as cart update is in progress");
            return;
        }
        
        // Also check via URL path for cart operations
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        if (strpos($request_uri, 'cart') !== false) {
            // If URL contains 'cart', this is likely a cart operation, not checkout
            \ShopMetrics_Logger::get_instance()->debug("Skipping checkout detection for URL containing 'cart': {$request_uri}");
            return;
        }
        
        // Only trigger if we're actually on the checkout page
        if (function_exists('is_checkout') && is_checkout() && !is_wc_endpoint_url('order-received')) {
            static $checkout_detected = false;
            
            // Only trigger once per page load
            if (!$checkout_detected) {
                $checkout_detected = true;
                \ShopMetrics_Logger::get_instance()->info("Checkout page detected via template_redirect hook");
                $this->handle_checkout_start();
            }
        }
    }

    /**
     * Handles the conversion of a cart to an order.
     *
     * @param int $order_id The order ID
     * @param array $posted_data The posted checkout data (optional)
     * @param WC_Order|null $order The order object (optional)
     */
    public function handle_order_conversion( $order_id, $posted_data = array(), $order = null ) {
        \ShopMetrics_Logger::get_instance()->info("handle_order_conversion triggered for order #{$order_id}");
        
        // Get order details if not provided
        if ( ! $order ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                \ShopMetrics_Logger::get_instance()->error("Failed to get order object for #{$order_id}");
                return;
            }
        }
        
        // Get user ID and session ID
        $user_id = get_current_user_id();
        $session_id = $this->get_or_create_session_id();
        
        // Get order total
        $order_total = $order->get_total();
        
        // Get cart hash if available, or generate a unique hash
        $cart_hash = '';
        $option_key = $this->get_cart_option_key( $user_id, $session_id );
        $stored_option_value = get_option( $option_key );
        
        if ( $stored_option_value && isset( $stored_option_value['data']['cart_hash'] ) ) {
            $cart_hash = $stored_option_value['data']['cart_hash'];
            \ShopMetrics_Logger::get_instance()->info("Found cart hash in stored option: {$cart_hash}");
        } else {
            // Generate a hash based on order ID and timestamp to ensure uniqueness
            $cart_hash = md5( $order_id . time() . $user_id . $session_id );
            \ShopMetrics_Logger::get_instance()->info("Generated cart hash for order: {$cart_hash}");
        }
        
        // After order is placed, remove the active cart data
        \ShopMetrics_Logger::get_instance()->info("Removing active cart option after conversion: {$option_key}");
        delete_option( $option_key );
        
        // Send order completion event with recovery source tracking info
        $recovery_source = isset($_COOKIE['shopmetrics_cart_recovery_source']) ? sanitize_text_field( wp_unslash( $_COOKIE['shopmetrics_cart_recovery_source'] ) ) : 'organic';
        
        $event_data = array(
            'order_id'        => $order_id,
            'user_id'         => $user_id,
            'session_id'      => $session_id,
            'recovery_source' => $recovery_source,
            'total_value'     => $order_total,
            'cart_hash'       => $cart_hash,
            'order_total'     => $order_total, // Include order_total as backup field
            'items'           => array() // Include empty items array to match cart structure
        );
        
        // Get order items to include in event data for better tracking
        $items = array();
        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            $items[] = array(
                'product_id' => $product ? $product->get_id() : 0,
                'name'       => $item->get_name(),
                'quantity'   => $item->get_quantity(),
                'price'      => $product ? $product->get_price() : 0
            );
        }
        
        $event_data['items'] = $items;
        $event_data['total_items'] = count($items);
        
        \ShopMetrics_Logger::get_instance()->info("Sending order_completed event with data: " . wp_json_encode($event_data));
        $this->send_cart_event( 'order_completed', $event_data );
        \ShopMetrics_Logger::get_instance()->info("order_completed event sent successfully");
        
        // Clear recovery source cookie if set
        if (isset($_COOKIE['shopmetrics_cart_recovery_source'])) {
            setcookie('shopmetrics_cart_recovery_source', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        }
    }

    /**
     * Checks for abandoned carts and sends events.
     *
     * @since 1.1.0
     */
    public function check_abandoned_carts() {
        \ShopMetrics_Logger::get_instance()->info("Running check_abandoned_carts cron job.");
        
        // Use WordPress Options API instead of direct database query
        $cache_key = 'shopmetrics_abandoned_cart_options';
        $active_cart_options = wp_cache_get($cache_key);
        
        if (false === $active_cart_options) {
            $active_cart_options = array();
            
            // Get all options that match our pattern using WordPress API
            $prefix = self::ACTIVE_CART_OPTION_PREFIX;
            
            // Get all options and filter for our pattern
            $all_options = wp_load_alloptions();
            foreach ($all_options as $option_name => $option_value) {
                if (strpos($option_name, $prefix) === 0) {
                    $active_cart_options[] = (object) array(
                        'option_name' => $option_name,
                        'option_value' => $option_value
                    );
                }
            }
            
            // Cache the result for 2 minutes
            wp_cache_set($cache_key, $active_cart_options, '', 120);
        }

        $abandonment_threshold_seconds = self::ABANDONMENT_THRESHOLD_HOURS * HOUR_IN_SECONDS;
        $now = time();
        $abandoned_count = 0;
        
        \ShopMetrics_Logger::get_instance()->info("Found " . count($active_cart_options) . " active cart options to check.");

        foreach ( $active_cart_options as $option ) {
            $cart_info = maybe_unserialize( $option->option_value );
            
            // Extract user_id and session_id from option name
            $option_name = $option->option_name;
            $user_id = 0;
            $session_id = '';
            
            // Parse user_id and session_id from option name
            if (strpos($option_name, self::ACTIVE_CART_OPTION_PREFIX . 'user_') === 0) {
                $user_id = intval(str_replace(self::ACTIVE_CART_OPTION_PREFIX . 'user_', '', $option_name));
                \ShopMetrics_Logger::get_instance()->info("Extracted user_id {$user_id} from option name {$option_name}");
            } elseif (strpos($option_name, self::ACTIVE_CART_OPTION_PREFIX . 'session_') === 0) {
                $session_id = str_replace(self::ACTIVE_CART_OPTION_PREFIX . 'session_', '', $option_name);
                \ShopMetrics_Logger::get_instance()->info("Extracted session_id {$session_id} from option name {$option_name}");
            } elseif (strpos($option_name, self::ACTIVE_CART_OPTION_PREFIX) === 0) {
                // Legacy format with cart_hash - try to extract from data
                if (isset($cart_info['data']['user_id'])) {
                    $user_id = $cart_info['data']['user_id'];
                }
                if (isset($cart_info['data']['session_id'])) {
                    $session_id = $cart_info['data']['session_id'];
                }
            }
            
            // Check if the cart data is valid
            if (empty($cart_info) || !isset($cart_info['timestamp']) || !isset($cart_info['data'])) {
                \ShopMetrics_Logger::get_instance()->info("Invalid data in active cart option: {$option->option_name}. Deleting.");
                delete_option($option->option_name);
                continue;
            }
            
            // Skip carts that already have user_session_key with "unknown_" prefix
            $data_user_id = isset($cart_info['data']['user_id']) ? $cart_info['data']['user_id'] : null;
            $data_session_id = isset($cart_info['data']['session_id']) ? $cart_info['data']['session_id'] : null;
            
            // Skip if we couldn't identify the user or session from either the option name or cart data
            if (empty($user_id) && empty($session_id) && empty($data_user_id) && empty($data_session_id)) {
                \ShopMetrics_Logger::get_instance()->warn("Cannot determine user or session for cart {$option->option_name}. Skipping.");
                continue;
            }
            
            // If we found IDs in the cart data but not in the option name, use those instead
            if (empty($user_id) && !empty($data_user_id)) {
                $user_id = $data_user_id;
                \ShopMetrics_Logger::get_instance()->info("Using user_id {$user_id} from cart data instead of option name.");
            }
            
            if (empty($session_id) && !empty($data_session_id)) {
                $session_id = $data_session_id;
                \ShopMetrics_Logger::get_instance()->info("Using session_id {$session_id} from cart data instead of option name.");
            }

            // Now check if the cart is abandoned based on timestamp
            if (($now - $cart_info['timestamp']) > $abandonment_threshold_seconds) {
                // Check if recovery email was already sent
                $email_option_key = $this->get_email_sent_option_key($user_id, $session_id);
                $email_sent = get_option($email_option_key, false);
                
                $identifier = !empty($user_id) && $user_id > 0 ? "User ID: {$user_id}" : "Session ID: {$session_id}";
                \ShopMetrics_Logger::get_instance()->info("Cart for {$identifier} is abandoned. Last activity: " . gmdate('Y-m-d H:i:s', $cart_info['timestamp']));

                $abandoned_event_data = $cart_info['data']; 
                
                // Make sure we include the correct user_id and session_id with the event
                if (!isset($abandoned_event_data['user_id']) || empty($abandoned_event_data['user_id'])) {
                    $abandoned_event_data['user_id'] = $user_id;
                }
                
                if (!isset($abandoned_event_data['session_id']) || empty($abandoned_event_data['session_id'])) {
                    $abandoned_event_data['session_id'] = $session_id;
                }
                
                $abandoned_event_data['abandoned_at'] = current_time('mysql', true); 
                $abandoned_event_data['email_sent'] = !empty($email_sent);
                
                // Make sure we only send the cart_abandoned event once per cart
                if (!isset($cart_info['abandoned_event_sent']) || !$cart_info['abandoned_event_sent']) {
                    $this->send_cart_event('cart_abandoned', $abandoned_event_data);
                    
                    // Update the cart_info to mark that we've sent the abandoned event
                    $cart_info['abandoned_event_sent'] = true;
                    update_option($option_name, $cart_info, 'no');
                    
                    $abandoned_count++;
                }
                
                // Don't delete the cart data yet - keep it for potential recovery
                // We'll track if an email was sent in a separate option
                if (!$email_sent) {
                    $this->maybe_send_recovery_email($user_id, $session_id, $cart_info['data']);
                }
            }
        }
        \ShopMetrics_Logger::get_instance()->info("Finished check_abandoned_carts cron job. Sent {$abandoned_count} cart_abandoned events.");
    }

    /**
     * Processes the debounced cart update.
     * This function is triggered by the scheduled single event.
     *
     * @param int $user_id
     * @param string $session_id
     * @param string $cart_data_json JSON string of the cart data snapshot
     */
    public function process_debounced_cart_update( $user_id, $session_id, $cart_data_json ) {
        \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartTracker (process_debounced_cart_update): Running for user {$user_id}, session {$session_id}.");

        $new_cart_data = json_decode( $cart_data_json, true );

        if ( !is_array($new_cart_data) ) { 
            \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartTracker (process_debounced_cart_update): Invalid cart data from JSON. Aborting. JSON was: " . $cart_data_json);
            return;
        }
        
        // Ensure the cart data contains the passed user_id and session_id
        // This is crucial for maintaining consistent identity tracking
        $new_cart_data['user_id'] = $user_id;
        $new_cart_data['session_id'] = $session_id;
        
        // Get appropriate option key based on user_id/session_id
        $option_key = $this->get_cart_option_key($user_id, $session_id);
        $stored_option_value = get_option($option_key);
        
        $has_meaningful_change = false;
        $current_timestamp = time();

        if ( $new_cart_data['is_empty'] ) {
            if ( false !== $stored_option_value ) {
                \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartTracker (process_debounced_cart_update): Cart is empty. Deleting option {$option_key}.");
                delete_option($option_key);
                
                // Also clean up any related email tracking options
                $email_option_key = $this->get_email_sent_option_key($user_id, $session_id);
                delete_option($email_option_key);
            }
            if (false !== $stored_option_value && isset($stored_option_value['data']) && !$stored_option_value['data']['is_empty']) {
                $has_meaningful_change = true;
                \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartTracker (process_debounced_cart_update): Cart became empty. Marking for event.");
            }
        } elseif ( false === $stored_option_value || !isset($stored_option_value['data']) ) {
            $has_meaningful_change = true;
            \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartTracker (process_debounced_cart_update): New cart for user {$user_id}, session {$session_id}. Marking for event.");
        } else {
            $stored_cart_data = $stored_option_value['data'];
            if ( wp_json_encode($stored_cart_data['items']) !== wp_json_encode($new_cart_data['items']) ||
                 (string)$stored_cart_data['total_value'] !== (string)$new_cart_data['total_value'] ||
                 $stored_cart_data['total_items'] !== $new_cart_data['total_items'] ) {
                $has_meaningful_change = true;
                \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartTracker (process_debounced_cart_update): Meaningful content change for user {$user_id}, session {$session_id}. Marking for event.");
            } else {
                \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartTracker (process_debounced_cart_update): No meaningful content change for user {$user_id}, session {$session_id}. Only updating timestamp.");
            }
        }

        if ( $has_meaningful_change ) {
            $option_to_store = array(
                'timestamp' => $current_timestamp,
                'data'      => $new_cart_data 
            );
            update_option($option_key, $option_to_store, 'no');
            
            // Double-check that these are properly set
            \ShopMetrics_Logger::get_instance()->debug("Sending cart_updated event with user_id: {$new_cart_data['user_id']}, session_id: {$new_cart_data['session_id']}");
            $this->send_cart_event('cart_updated', $new_cart_data);
        } elseif ( false !== $stored_option_value && !$new_cart_data['is_empty'] ) {
            $option_to_store = array(
                'timestamp' => $current_timestamp,
                'data'      => $stored_option_value['data'] 
            );
            update_option($option_key, $option_to_store, 'no');
            \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartTracker (process_debounced_cart_update): Updated timestamp for active cart (no content change) for user {$user_id}, session {$session_id}.");
        }
    }

    /**
     * Gets the current WooCommerce session ID or creates a new one if needed
     * 
     * @return string The WooCommerce session ID
     */
    private function get_or_create_session_id() {
        // Check if WooCommerce is active and session is available
        if ( function_exists( 'WC' ) && WC()->session ) {
            $session_id = WC()->session->get_customer_id();
            if ( !empty( $session_id ) ) {
                return $session_id;
            }
        }
        
        // If no session ID is available, try to get it from cookies
        if ( isset( $_COOKIE['wp_woocommerce_session'] ) ) {
            $cookie_value = sanitize_text_field( wp_unslash( $_COOKIE['wp_woocommerce_session'] ) );
            $session_parts = explode( '||', $cookie_value );
            if ( !empty( $session_parts[0] ) ) {
                return $session_parts[0];
            }
        }
        
        // As a last resort, generate a unique ID
        return 'tmp_' . md5( uniqid( wp_rand(), true ) );
    }

    /**
     * Placeholder for sending recovery emails - to be fully implemented
     * 
     * @param int $user_id The user ID
     * @param string $session_id The session ID
     * @param array $cart_data The cart data
     */
    private function maybe_send_recovery_email($user_id, $session_id, $cart_data) {
        // Check if cart recovery emails are enabled
        $settings = get_option('shopmetrics_settings', []);
        $emails_enabled = !empty($settings['enable_cart_recovery_emails']);
        if (!$emails_enabled) {
            return;
        }
        
        // Get customer email
        $customer_email = '';
        
        // For registered users
        if ($user_id > 0) {
            $user = get_user_by('id', $user_id);
            if ($user && !empty($user->user_email)) {
                $customer_email = $user->user_email;
            }
        }
        
        // For guest users with session, try to get from WooCommerce session or order history
        if (empty($customer_email) && !empty($session_id)) {
            // This would need more implementation to extract email from session data
            // Or from a previously submitted checkout form
        }
        
        // If we have a valid email, send the recovery email
        if (!empty($customer_email) && is_email($customer_email)) {
            \ShopMetrics_Logger::get_instance()->info("Would send recovery email to {$customer_email} for abandoned cart");
            
            // TODO: Implement actual email sending here
            
            // Mark that we've sent an email for this cart
            $email_option_key = $this->get_email_sent_option_key($user_id, $session_id);
            update_option($email_option_key, time(), 'no');
        }
    }
}