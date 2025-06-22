<?php

namespace ShopMetrics\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Manages automatic order synchronization using WordPress/WooCommerce action hooks.
 *
 * @since      1.1.0
 * @package    ShopMetrics
 * @subpackage ShopMetrics/includes
 * @author     FinanciarMe <info@financiarme.es>
 */
class ShopMetrics_Order_Sync_Manager {

    /**
     * Reference to the main plugin class instance.
     * @var ShopMetrics
     */
    protected $plugin;

    /**
     * Constructor.
     * @param \ShopMetrics\Analytics\ShopMetrics $plugin Main plugin instance.
     */
    public function __construct( \ShopMetrics\Analytics\ShopMetrics $plugin ) {
        $this->plugin = $plugin;
    }

    /**
     * Initialize order synchronization hooks.
     *
     * @since 1.1.0
     */
    public function init_hooks() {
        // Only hook if we have API credentials
        if ( ! $this->has_api_credentials() ) {
            \ShopMetrics_Logger::get_instance()->debug( 'Order sync hooks not initialized - missing API credentials' );
            return;
        }

        // Regular WordPress hooks DISABLED - Using REST API hooks instead to avoid duplicates
        // The REST API hooks below provide complete coverage and proper historical date handling
        // add_action( 'woocommerce_new_order', array( $this, 'handle_order_created' ), 50, 2 );
        // add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_updated' ), 50, 4 );
        // add_action( 'woocommerce_order_refunded', array( $this, 'handle_order_refund' ), 50, 2 );
        
        // CRITICAL: Hook into REST API events to catch orders after historical dates plugin has processed them
        // This is essential for orders created via our generator which uses the WooCommerce REST API
        add_action( 'woocommerce_rest_insert_shop_order_object', array( $this, 'handle_rest_order_created' ), 999, 3 );
        add_action( 'woocommerce_rest_update_shop_order_object', array( $this, 'handle_rest_order_updated' ), 999, 3 );
        
        // Note: Removed woocommerce_delete_order hook - deleted orders don't need to be tracked
        // as they're usually test orders or spam and don't contribute to business analytics

        \ShopMetrics_Logger::get_instance()->info( 'Order sync hooks initialized - Using REST API hooks (priority 999) for proper historical date handling' );
    }

    /**
     * Check if API credentials are available.
     *
     * @since 1.1.0
     * @return bool
     */
    private function has_api_credentials() {
        $api_token = get_option( 'shopmetrics_analytics_api_token', '' );
        $site_identifier = get_option( 'shopmetrics_analytics_site_identifier', '' );
        
        return ! empty( $api_token ) && ! empty( $site_identifier );
    }

    /**
     * Handle new order creation.
     *
     * @since 1.1.0
     * @param int $order_id Order ID
     * @param \WC_Order $order Order object
     */
    public function handle_order_created( $order_id, $order ) {
        \ShopMetrics_Logger::get_instance()->info( "Handling order created: {$order_id}" );
        
        try {
            $this->sync_order_to_backend( $order, 'created' );
        } catch ( Exception $e ) {
            \ShopMetrics_Logger::get_instance()->error( "Failed to sync created order {$order_id}: " . $e->getMessage() );
        }
    }

    /**
     * Handle order status changes.
     *
     * @since 1.1.0
     * @param int $order_id Order ID
     * @param string $old_status Old order status
     * @param string $new_status New order status
     * @param \WC_Order $order Order object
     */
    public function handle_order_updated( $order_id, $old_status, $new_status, $order ) {
        \ShopMetrics_Logger::get_instance()->info( "Handling order updated: {$order_id} ({$old_status} -> {$new_status})" );
        
        try {
            $this->sync_order_to_backend( $order, 'updated' );
        } catch ( Exception $e ) {
            \ShopMetrics_Logger::get_instance()->error( "Failed to sync updated order {$order_id}: " . $e->getMessage() );
        }
    }

    // Note: Order deletion handling removed - deleted orders don't need database tracking

    /**
     * Handle order refunds.
     *
     * @since 1.1.0
     * @param int $order_id Order ID
     * @param int $refund_id Refund ID
     */
    public function handle_order_refund( $order_id, $refund_id ) {
        \ShopMetrics_Logger::get_instance()->info( "Handling order refund: {$order_id} (refund: {$refund_id})" );
        
        try {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $this->sync_order_to_backend( $order, 'refunded' );
            }
        } catch ( Exception $e ) {
            \ShopMetrics_Logger::get_instance()->error( "Failed to sync refunded order {$order_id}: " . $e->getMessage() );
        }
    }

    /**
     * Handle REST API order creation (runs AFTER historical dates plugin).
     *
     * @since 1.1.0
     * @param \WC_Order $order Order object
     * @param \WP_REST_Request $request REST request
     * @param bool $creating Whether this is a new order creation
     */
    public function handle_rest_order_created( $order, $request, $creating ) {
        if ( ! $creating || ! $order || ! is_a( $order, 'WC_Order' ) ) {
            return;
        }
        
        \ShopMetrics_Logger::get_instance()->info( "Handling REST API order created: {$order->get_id()} (after historical dates processing)" );
        
        try {
            $this->sync_order_to_backend( $order, 'rest_created' );
        } catch ( Exception $e ) {
            \ShopMetrics_Logger::get_instance()->error( "Failed to sync REST created order {$order->get_id()}: " . $e->getMessage() );
        }
    }

    /**
     * Handle REST API order updates (runs AFTER historical dates plugin).
     *
     * @since 1.1.0
     * @param \WC_Order $order Order object
     * @param \WP_REST_Request $request REST request
     * @param bool $creating Whether this is a new order creation
     */
    public function handle_rest_order_updated( $order, $request, $creating ) {
        if ( $creating || ! $order || ! is_a( $order, 'WC_Order' ) ) {
            return;
        }
        
        \ShopMetrics_Logger::get_instance()->info( "Handling REST API order updated: {$order->get_id()} (after historical dates processing)" );
        
        try {
            $this->sync_order_to_backend( $order, 'rest_updated' );
        } catch ( Exception $e ) {
            \ShopMetrics_Logger::get_instance()->error( "Failed to sync REST updated order {$order->get_id()}: " . $e->getMessage() );
        }
    }

    /**
     * Sync order to backend using existing orders tracker.
     *
     * @since 1.1.0
     * @param \WC_Order $order Order object
     * @param string $action Action type (created, updated, refunded)
     */
    private function sync_order_to_backend( $order, $action ) {
        if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
            \ShopMetrics_Logger::get_instance()->error( "Invalid order object for sync" );
            return;
        }

        // Get orders tracker instance from plugin
        $orders_tracker = $this->plugin->get_orders_tracker();
        if ( ! $orders_tracker ) {
            \ShopMetrics_Logger::get_instance()->error( "Orders tracker not available for sync" );
            return;
        }
        
        // Collect order data using existing logic
        $order_data = $orders_tracker->get_order_data( $order );
        
        if ( empty( $order_data ) ) {
            \ShopMetrics_Logger::get_instance()->error( "No order data collected for order: " . $order->get_id() );
            return;
        }

        // Log detailed date information to debug the aggregation issue
        $date_to_send = $order_data['date_created'] ?? 'not found';
        $wc_date_created = $order->get_date_created() ? $order->get_date_created()->date('c') : 'no date';
        $wc_date_modified = $order->get_date_modified() ? $order->get_date_modified()->date('c') : 'no date';
        \ShopMetrics_Logger::get_instance()->info( "Syncing order {$order->get_id()}:" );
        \ShopMetrics_Logger::get_instance()->info( "  WC date_created:  {$wc_date_created}" );
        \ShopMetrics_Logger::get_instance()->info( "  WC date_modified: {$wc_date_modified}" );
        \ShopMetrics_Logger::get_instance()->info( "  Payload date:     {$date_to_send}" );

        // Add action metadata
        $order_data['sync_action'] = $action;
        $order_data['sync_timestamp'] = current_time( 'mysql', true );
        $order_data['sync_method'] = 'auto_hook';

        // Send to backend
        $this->send_to_backend( $order_data, $action );
    }

    // Note: Order deletion sync method removed - deleted orders don't need database tracking

    /**
     * Send order data to backend API.
     *
     * @since 1.1.0
     * @param array $order_data Order data
     * @param string $action Action type
     */
    private function send_to_backend( $order_data, $action ) {
        $api_token = get_option( 'shopmetrics_analytics_api_token', '' );
        $site_identifier = get_option( 'shopmetrics_analytics_site_identifier', '' );

        if ( empty( $api_token ) || empty( $site_identifier ) ) {
            \ShopMetrics_Logger::get_instance()->error( "Cannot sync order - missing API credentials" );
            return;
        }

        $endpoint_url = SHOPMETRICS_API_URL . '/v1/orders';
        
        $request_args = array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-FinanciarMe-Token' => $api_token,
                'X-FinanciarMe-Site-Identifier' => $site_identifier,
            ),
            'body' => wp_json_encode( $order_data ),
        );

        \ShopMetrics_Logger::get_instance()->debug( "Sending {$action} order to backend: " . $endpoint_url );

        $response = wp_remote_post( $endpoint_url, $request_args );

        if ( is_wp_error( $response ) ) {
            \ShopMetrics_Logger::get_instance()->error( "HTTP error syncing {$action} order: " . $response->get_error_message() );
            return;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        if ( $response_code >= 200 && $response_code < 300 ) {
            \ShopMetrics_Logger::get_instance()->info( "Successfully synced {$action} order to backend (HTTP {$response_code})" );
        } else {
            \ShopMetrics_Logger::get_instance()->error( "Backend error syncing {$action} order (HTTP {$response_code}): {$response_body}" );
        }
    }

    /**
     * Get sync status information.
     *
     * @since 1.1.0
     * @return array Sync status data
     */
    public function get_sync_status() {
        $has_credentials = $this->has_api_credentials();
        $hooks_active = $has_credentials && has_action( 'woocommerce_new_order', array( $this, 'handle_order_created' ) );

        return array(
            'enabled' => $hooks_active,
            'has_credentials' => $has_credentials,
            'method' => 'wordpress_hooks',
            'hooks' => array(
                'woocommerce_new_order' => has_action( 'woocommerce_new_order', array( $this, 'handle_order_created' ) ),
                'woocommerce_order_status_changed' => has_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_updated' ) ),
                'woocommerce_order_refunded' => has_action( 'woocommerce_order_refunded', array( $this, 'handle_order_refund' ) ),
                // Note: woocommerce_delete_order hook removed - deleted orders don't need tracking
            )
        );
    }

    /**
     * Test order sync connectivity.
     *
     * @since 1.1.0
     * @return array Test results
     */
    public function test_sync_connectivity() {
        $results = array(
            'success' => false,
            'message' => '',
            'details' => array()
        );

        $api_token = get_option( 'shopmetrics_analytics_api_token', '' );
        $site_identifier = get_option( 'shopmetrics_analytics_site_identifier', '' );

        if ( empty( $api_token ) || empty( $site_identifier ) ) {
            $results['message'] = 'Missing API credentials';
            return $results;
        }

        // Test endpoint connectivity
        $test_url = SHOPMETRICS_API_URL . '/v1/orders';
        $test_data = array(
            'test' => true,
            'order_id' => 'sync-test-' . time(),
            'sync_method' => 'auto_hook'
        );

        $response = wp_remote_post( $test_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-FinanciarMe-Token' => $api_token,
                'X-FinanciarMe-Site-Identifier' => $site_identifier
            ),
            'body' => wp_json_encode( $test_data ),
            'timeout' => 15
        ) );

        if ( is_wp_error( $response ) ) {
            $results['message'] = 'Connection failed: ' . $response->get_error_message();
            return $results;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $results['details']['response_code'] = $response_code;
        $results['details']['response_body'] = wp_remote_retrieve_body( $response );

        if ( $response_code >= 200 && $response_code < 300 ) {
            $results['success'] = true;
            $results['message'] = 'Order sync endpoint is reachable';
        } else {
            $results['message'] = "Order sync endpoint returned HTTP {$response_code}";
        }

        return $results;
    }
} 