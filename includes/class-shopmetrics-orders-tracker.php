<?php

namespace ShopMetrics\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Handles order-related event tracking and asynchronous processing.
 *
 * @since      1.1.0
 * @package    ShopMetrics
 * @subpackage ShopMetrics/includes
 * @author     FinanciarMe <info@financiarme.es>
 */
class ShopMetrics_Orders_Tracker {

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
     * Initialize WordPress and WooCommerce hooks for order events.
     *
     * @since    1.1.0
     */
    public function init_hooks() {
        // Note: woocommerce_thankyou hook removed - now handled by Order_Sync_Manager
        // with more reliable woocommerce_new_order hook that covers ALL order creation methods
        
        // When an order status changes
        add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_change' ), 10, 3 );
        // When an order is refunded
        add_action( 'woocommerce_order_refunded', array( $this, 'handle_order_refunded' ), 10, 2 );
    }

    /**
     * Initialize Action Scheduler hooks for background order data processing.
     *
     * @since    1.1.0
     */
    public function init_async_hooks() {
        // Hook for sending individual order data asynchronously
        add_action( 'shopmetrics_analytics_send_order_data', array( $this, 'send_order_data_to_backend' ), 10, 1 );
        // Hook for sending order status updates asynchronously
        add_action( 'shopmetrics_analytics_send_status_update', array( $this, 'send_status_update_to_backend' ), 10, 3 );
        // Hook for sending order refund data asynchronously
        add_action( 'shopmetrics_analytics_send_refund_data', array( $this, 'send_refund_data_to_backend' ), 10, 2 );
        // Hook for processing historical orders synchronization
        add_action( 'shopmetrics_analytics_sync_historical_orders', array( $this, 'sync_historical_orders' ), 10 );
    }

    /**
     * Handle processing when a new order is viewed on the Thank You page.
     * Schedules a background task to send order data asynchronously.
     *
     * @since    1.0.0 (Moved to Orders_Tracker 1.1.0)
     * @param    int    $order_id    The ID of the order.
     */
    public function handle_new_order( $order_id ) {
        		\ShopMetrics_Logger::get_instance()->debug("handle_new_order (thankyou) called for order_id: " . $order_id);
        
        if ( ! function_exists( 'as_enqueue_async_action' ) ) {
            			\ShopMetrics_Logger::get_instance()->error("Action Scheduler not found. Cannot schedule async send for order " . $order_id);
            return;
        }

        if ( ! $order_id ) {
            			\ShopMetrics_Logger::get_instance()->warn("Invalid order_id received: " . $order_id);
            return;
        }
        
        // Trigger cart completion event for checkout funnel tracking
        $cart_tracker = $this->plugin->get_cart_tracker();
        if ($cart_tracker) {
            		\ShopMetrics_Logger::get_instance()->debug("Notifying Cart_Tracker of order completion for order_id: " . $order_id);
            $order = wc_get_order($order_id);
            if ($order) {
                $cart_tracker->handle_order_conversion($order_id, array(), $order);
            } else {
                				\ShopMetrics_Logger::get_instance()->warn("Failed to get order object for cart tracking integration");
            }
        } else {
            			\ShopMetrics_Logger::get_instance()->debug("Cart_Tracker not available for checkout funnel tracking");
        }
        
        $action_hook = 'shopmetrics_analytics_send_order_data';
        $args = array( 'order_id' => $order_id );
        $group = 'shopmetrics-orders';

        $scheduled_actions = as_get_scheduled_actions( array(
            'hook' => $action_hook,
            'args' => $args,
            'status' => array( \ActionScheduler_Store::STATUS_PENDING, \ActionScheduler_Store::STATUS_RUNNING ),
            'per_page' => 1,
        ), 'ids' );

        if ( empty( $scheduled_actions ) ) {
            $action_id = as_enqueue_async_action( $action_hook, $args, $group );
            if ( $action_id ) {
                			\ShopMetrics_Logger::get_instance()->info("Successfully scheduled async send for order_id: $order_id (Action ID: $action_id)");
            } else {
                				\ShopMetrics_Logger::get_instance()->error("Failed to schedule async send for order_id: $order_id");
            }
        } else {
             			\ShopMetrics_Logger::get_instance()->debug("Async send already scheduled/running for order_id: $order_id");
        }
    }
    
    /**
     * Sends the data for a specific order to the backend API using Api_Client.
     * (Payload preserved to match original structure as per Option B)
     * This function is designed to be called by Action Scheduler.
     *
     * @since 1.0.0 (Moved to Orders_Tracker 1.1.0)
     * @param int $order_id The ID of the order to send.
     */
    public function send_order_data_to_backend( $order_id ) {
        \ShopMetrics_Logger::get_instance()->info('send_order_data_to_backend called for order_id: ' . $order_id);

        $subscription_status = get_option( 'shopmetrics_subscription_status', 'unknown' );
        $allow_send = false;
        $current_time = time();

        if ( $subscription_status === 'active' || $subscription_status === 'pending_cancellation') {
            $allow_send = true;
        } elseif ( $subscription_status === 'free' ) {
            $allow_send = true; // Free tier can send orders
        } else {
            \ShopMetrics_Logger::get_instance()->info("Subscription status is '{$subscription_status}'. Skipping send for order ID: {$order_id}");
        }

        if ( ! $allow_send ) {
            \ShopMetrics_Logger::get_instance()->info("Sending not allowed due to subscription status for order ID: {$order_id}");
            return;
        }

        		\ShopMetrics_Logger::get_instance()->debug("Starting send_order_data_to_backend for Order ID: " . $order_id);

        if ( ! $order_id ) {
            \ShopMetrics_Logger::get_instance()->debug("Invalid order_id received: " . $order_id);
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
             		\ShopMetrics_Logger::get_instance()->error( "Could not get order object for ID: " . $order_id );
            return;
        }
        \ShopMetrics_Logger::get_instance()->debug("Successfully retrieved WC_Order object for order_id: " . $order_id);

        $customer_id_from_order = $order->get_customer_id();
        $billing_email = $order->get_billing_email();
        $unique_customer_identifier = '';
        $secret_salt = $this->get_customer_hash_salt(); 

        if ( $customer_id_from_order > 0 ) {
            $unique_customer_identifier = 'user_' . $customer_id_from_order;
        } elseif ( ! empty( $billing_email ) ) {
            $unique_customer_identifier = 'guest_' . hash_hmac('sha256', strtolower(trim($billing_email)), $secret_salt);
        }

        $settings = get_option('shopmetrics_settings', []);
        $user_cogs_meta_key = $settings['cogs_meta_key'] ?? '';
        $default_cogs_percentage = null;
        if (isset($settings['cogs_default_percentage']) && is_numeric($settings['cogs_default_percentage'])) {
            $temp_percentage = floatval($settings['cogs_default_percentage']);
            if ($temp_percentage >= 0 && $temp_percentage <= 100) {
                $default_cogs_percentage = $temp_percentage;
            }
        }
        $known_cogs_fallback_keys = array( '_wc_cog_item_cost', 'cost_of_goods' );
 
        $line_items_data = array();
        $order_items = $order->get_items();

        foreach ( $order_items as $item_id => $item ) {
            /** @var \WC_Order_Item_Product $item */
            $product = $item->get_product();
            $cogs_for_item = null;
            $cogs_reason = '';

            // --- Add categories ---
            $categories = array();
            if ( $product && $product->get_id() ) {
                $terms = get_the_terms( $product->get_id(), 'product_cat' );
                if ( $terms && ! is_wp_error( $terms ) ) {
                    foreach ( $terms as $term ) {
                        $categories[] = $term->name;
                    }
                }
                // Debug log for categories
                \ShopMetrics_Logger::get_instance()->info('Product ID ' . $product->get_id() . ' (' . $product->get_name() . ') categories: ' . json_encode($categories));
            } else {
                \ShopMetrics_Logger::get_instance()->info('No product or invalid product ID for item_id ' . $item_id);
            }
            // --- End categories ---

            // 1. Order item meta (выбранный ключ)
            if ( ! empty( $user_cogs_meta_key ) ) {
                $cogs_value_meta = $item->get_meta( $user_cogs_meta_key, true );
                if ( $cogs_value_meta !== '' && is_numeric( $cogs_value_meta ) && floatval( $cogs_value_meta ) > 0 ) {
                    $cogs_for_item = floatval( $cogs_value_meta );
                    $cogs_reason = 'order_item_meta_key';
                }
            }
            // 2. Fallback-ключи (order item meta)
            if ( is_null( $cogs_for_item ) ) {
                foreach ( $known_cogs_fallback_keys as $f_key ) {
                    $cogs_value_fallback = $item->get_meta( $f_key, true );
                    if ( $cogs_value_fallback !== '' && is_numeric( $cogs_value_fallback ) && floatval( $cogs_value_fallback ) > 0 ) {
                        $cogs_for_item = floatval( $cogs_value_fallback );
                        $cogs_reason = 'order_item_fallback_key:' . $f_key;
                        break;
                    }
                }
            }
            // 3. Default процент
            if ( is_null( $cogs_for_item ) && !is_null( $default_cogs_percentage ) ) {
                $quantity = $item->get_quantity();
                if ( $quantity > 0 ) {
                    $price_per_unit = floatval( $item->get_subtotal() ) / $quantity;
                    $cogs_for_item = $price_per_unit * ( $default_cogs_percentage / 100.0 );
                    $cogs_reason = 'default_percentage';
                }
            }
            // 4. Если ничего не найдено — ставим 0 и логируем
            if ( is_null( $cogs_for_item ) ) {
                $cogs_for_item = 0;
                $cogs_reason = 'not_found';
            }
            // Логируем детали для каждого item
            \ShopMetrics_Logger::get_instance()->info('[ShopMetrics COGS] item_id=' . $item_id . ', product_id=' . $item->get_product_id() . ', meta_key=' . $user_cogs_meta_key . ', default_percent=' . wp_json_encode($default_cogs_percentage) . ', cogs=' . wp_json_encode($cogs_for_item) . ', reason=' . $cogs_reason);
            $line_items_data[] = array(
                'item_id'      => $item_id,
                'product_id'   => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'name'         => $item->get_name(),
                'quantity'     => $item->get_quantity(),
                'subtotal'     => $item->get_subtotal(),
                'total'        => $item->get_total(),
                'sku'          => $product ? $product->get_sku() : null,
                'cogs'         => round(floatval( $cogs_for_item ), 2),
                'categories'   => $categories,
            );
        }

        $payload = array(
            'order_id'                 => $order->get_id(),
            'order_key'                => $order->get_order_key(),
            'status'                   => $order->get_status(),
            'date_created'             => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : null,
            'date_modified'            => $order->get_date_modified() ? $order->get_date_modified()->date( 'c' ) : null,
            'total'                    => $order->get_total(),
            'currency'                 => $order->get_currency(),
            'customer_id'              => $customer_id_from_order, 
            'unique_customer_identifier' => $unique_customer_identifier,
            'customer_ip_address'      => $order->get_customer_ip_address(),
            'customer_user_agent'      => $order->get_customer_user_agent(),
            'payment_method'           => $order->get_payment_method(),
            'payment_method_title'     => $order->get_payment_method_title(),
            'shipping_total'           => $order->get_shipping_total(),
            'shipping_tax'             => $order->get_shipping_tax(),
            'discount_total'           => $order->get_discount_total(),
            'discount_tax'             => $order->get_discount_tax(),
            'total_tax'                => $order->get_total_tax(),
            'billing_address' => array(
                'city'       => $order->get_billing_city(),
                'country'    => $this->get_country_name_from_code( $order->get_billing_country() ),
            ),
            'is_logged_in_customer' => $customer_id_from_order > 0,
            'items'                    => $line_items_data,
        );
        
        // Before sending, log the full payload for debugging
        \ShopMetrics_Logger::get_instance()->info('[ShopMetrics COGS] FINAL ORDER PAYLOAD order_id=' . $order_id . ': ' . wp_json_encode($payload));
        $response = ShopMetrics_Api_Client::send_request( 'v1/orders', $payload, 'POST' );

        if ( is_wp_error( $response ) ) {
            \ShopMetrics_Logger::get_instance()->error( "Error sending order data for Order ID {$order_id}: " . $response->get_error_message() );
        } else {
             $log_message = "FinanciarMe Orders_Tracker: Successfully sent order data for Order ID {$order_id}.";
            if (is_array($response) || is_object($response)) {
                $log_message .= " Response: " . wp_json_encode($response);
            } elseif (true === $response) { 
                $log_message .= " Response: OK (No body).";
            }
            \ShopMetrics_Logger::get_instance()->info($log_message);
        }
        \ShopMetrics_Logger::get_instance()->debug("Finished send_order_data_to_backend for Order ID: " . $order_id);
    }

    /**
     * Handle processing when an order status changes.
     * Schedules a background task to send the status update asynchronously.
     *
     * @since    1.0.0 (Moved to Orders_Tracker 1.1.0)
     * @param    int      $order_id    The ID of the order.
     * @param    string   $old_status  The old order status.
     * @param    string   $new_status  The new order status.
     */
    public function handle_order_status_change( $order_id, $old_status, $new_status ) {
        \ShopMetrics_Logger::get_instance()->debug("handle_order_status_change called for order_id: $order_id, from '$old_status' to '$new_status'.");

        if ( ! function_exists( 'as_enqueue_async_action' ) ) {
            \ShopMetrics_Logger::get_instance()->debug("Action Scheduler not found. Cannot schedule async status update for order " . $order_id);
            return;
        }

        if ( ! $order_id ) {
            \ShopMetrics_Logger::get_instance()->debug("Invalid order_id received in status change: " . $order_id);
            return;
        }

        if ( $old_status === $new_status ) {
             \ShopMetrics_Logger::get_instance()->debug("Order status unchanged ($new_status), skipping async schedule for order_id: $order_id");
             return;
        }

        $action_hook = 'shopmetrics_analytics_send_status_update';
        $args = array(
            'order_id'   => $order_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
        );
        $group = 'shopmetrics-status-updates';

        $scheduled_actions = as_get_scheduled_actions( array(
            'hook'     => $action_hook,
            'args'     => $args,
            'status'   => array( \ActionScheduler_Store::STATUS_PENDING, \ActionScheduler_Store::STATUS_RUNNING ),
            'group'    => $group,
            'per_page' => 1,
        ), 'ids' );

        if ( empty( $scheduled_actions ) ) {
            $action_id = as_enqueue_async_action( $action_hook, $args, $group );
            if ( $action_id ) {
                \ShopMetrics_Logger::get_instance()->debug("Successfully scheduled async status update for order_id: $order_id ('$old_status' -> '$new_status'). Action ID: $action_id");
            } else {
                \ShopMetrics_Logger::get_instance()->debug("Failed to schedule async status update for order_id: $order_id ('$old_status' -> '$new_status').");
            }
        } else {
             \ShopMetrics_Logger::get_instance()->debug("Async status update ('$old_status' -> '$new_status') already scheduled/running for order_id: $order_id");
        }
        // Always send full order data as well
        $this->handle_new_order($order_id);
    }

    /**
     * Sends the status update for a specific order to the backend API using Api_Client.
     * This function is designed to be called by Action Scheduler.
     *
     * @since 1.0.0 (Moved to Orders_Tracker 1.1.0)
     * @param int    $order_id    The ID of the order.
     * @param string $old_status  The previous status.
     * @param string $new_status  The new status.
     */
    public function send_status_update_to_backend( $order_id, $old_status, $new_status ) {
        \ShopMetrics_Logger::get_instance()->debug("Starting send_status_update for order_id: $order_id, '$old_status' -> '$new_status'");

        if ( ! $order_id || empty($new_status) ) { 
            \ShopMetrics_Logger::get_instance()->debug("Invalid arguments received for status update: OrderID=$order_id, New=$new_status");
            return; 
        }
        
        $payload = array(
            'order_id_source_system' => $order_id,
            'new_status'             => $new_status,
            'old_status'             => $old_status,
            'event_timestamp_gmt'    => current_time( 'mysql', true ),
        );

        \ShopMetrics_Logger::get_instance()->debug("Payload for status update order_id: $order_id - " . wp_json_encode($payload));
        $response = ShopMetrics_Api_Client::send_request( 'v1/orders', $payload, 'PUT' ); 

        if ( is_wp_error( $response ) ) {
            \ShopMetrics_Logger::get_instance()->error( "Error sending status update for Order ID {$order_id}: " . $response->get_error_message() );
        } else {
            $log_message = "Successfully sent status update for Order ID {$order_id} ('$old_status' -> '$new_status').";
            if (is_array($response) || is_object($response)) {
                $log_message .= " Response: " . wp_json_encode($response);
            } elseif (true === $response) {
                $log_message .= " Response: OK (No body).";
            }
            \ShopMetrics_Logger::get_instance()->info($log_message);
        }
    }

    /**
     * Handle processing when an order is refunded.
     * Schedules a background task to send refund data asynchronously.
     *
     * @since    1.0.0 (Moved to Orders_Tracker 1.1.0)
     * @param    int    $order_id     The ID of the order being refunded.
     * @param    int    $refund_id    The ID of the refund object itself.
     */
    public function handle_order_refunded( $order_id, $refund_id ) {
        \ShopMetrics_Logger::get_instance()->debug("handle_order_refunded called for order_id: $order_id, refund_id: $refund_id.");

        if ( ! function_exists( 'as_enqueue_async_action' ) ) {
            \ShopMetrics_Logger::get_instance()->debug("Action Scheduler not found. Cannot schedule async refund send for order " . $order_id);
            return;
        }

        if ( ! $order_id || ! $refund_id ) {
            \ShopMetrics_Logger::get_instance()->debug("Invalid order_id ($order_id) or refund_id ($refund_id) received.");
            return;
        }

        $action_hook = 'shopmetrics_analytics_send_refund_data';
        $args = array(
            'order_id'   => $order_id,
            'refund_id'  => $refund_id,
        );
        $group = 'shopmetrics-refunds';

        $scheduled_actions = as_get_scheduled_actions( array(
            'hook'     => $action_hook,
            'args'     => $args,
            'status'   => array( \ActionScheduler_Store::STATUS_PENDING, \ActionScheduler_Store::STATUS_RUNNING ),
            'group'    => $group,
            'per_page' => 1,
        ), 'ids' );

        if ( empty( $scheduled_actions ) ) {
            $action_id = as_enqueue_async_action( $action_hook, $args, $group );
            if ( $action_id ) {
                \ShopMetrics_Logger::get_instance()->debug("Successfully scheduled async refund send for order_id: $order_id, refund_id: $refund_id. Action ID: $action_id");
            } else {
                \ShopMetrics_Logger::get_instance()->debug("Failed to schedule async refund send for order_id: $order_id, refund_id: $refund_id.");
            }
        } else {
             \ShopMetrics_Logger::get_instance()->debug("Async refund send already scheduled/running for order_id: $order_id, refund_id: $refund_id.");
        }
    }

    /**
     * Sends the refund data for a specific order/refund to the backend API.
     * This function is designed to be called by Action Scheduler.
     *
     * @since 1.0.0 (Moved to Orders_Tracker 1.1.0)
     * @param int $order_id   The ID of the original order.
     * @param int $refund_id  The ID of the refund object.
     */
    public function send_refund_data_to_backend( $order_id, $refund_id ) {
        \ShopMetrics_Logger::get_instance()->debug("Starting send_refund_data for order_id: $order_id, refund_id: $refund_id");

        if ( ! $order_id || ! $refund_id ) {
            \ShopMetrics_Logger::get_instance()->debug("Invalid order_id ($order_id) or refund_id ($refund_id) received for refund send.");
            return; 
        }

        $refund = wc_get_order( $refund_id );

        if ( ! $refund || ! is_a( $refund, 'WC_Order_Refund' ) ) {
            \ShopMetrics_Logger::get_instance()->debug("Could not retrieve WC_Order_Refund object for refund_id: $refund_id");
            return;
        }
        
        $refund_data = array(
            'action_type'   => 'refund', // To tell backend how to handle this PUT
            'order_id'      => $order_id, 
            'refund_id'     => $refund->get_id(),
            'amount'        => $refund->get_amount(),
            'reason'        => $refund->get_reason() ? $refund->get_reason() : null,
            'date_created'  => $refund->get_date_created() ? $refund->get_date_created()->date('c') : null,
        );

        \ShopMetrics_Logger::get_instance()->debug("Preparing to send refund data for order_id: $order_id, refund_id: $refund_id. Payload: " . wp_json_encode($refund_data));
        
        // Using ShopMetrics_Api_Client to send the request
        $response = ShopMetrics_Api_Client::send_request( 'v1/orders', $refund_data, 'PUT' );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            \ShopMetrics_Logger::get_instance()->error( "Error sending refund data to API for order $order_id, refund $refund_id: " . $error_message );
        } else {
            $log_message = "Sent refund data to API for order $order_id, refund $refund_id.";
            if (is_array($response) || is_object($response)) {
                $log_message .= " Response: " . wp_json_encode($response);
            } elseif (true === $response) {
                $log_message .= " Response: OK (No body).";
            }
            \ShopMetrics_Logger::get_instance()->info($log_message);
        }
    }

    /**
     * Retrieves the customer hash salt, generating and storing it if not present.
     *
     * @since 1.0.0 (Moved to Orders_Tracker 1.1.0)
     * @return string The customer hash salt.
     */
    private function get_customer_hash_salt() {
        $salt = get_option( 'shopmetrics_analytics_customer_hash_salt' );
        if ( ! $salt ) {
            $salt = wp_generate_password( 64, true, true );
            update_option( 'shopmetrics_analytics_customer_hash_salt', $salt, 'yes' );
        }
        return $salt;
    }

    /**
	 * Helper function to get the full country name from its 2-letter code.
	 *
     * @since 1.0.0 (Moved to Orders_Tracker 1.1.0)
	 * @param string $country_code The 2-letter country code.
	 * @return string The full country name, or the code itself if not found.
	 */
	private function get_country_name_from_code( $country_code ) {
		if ( empty( $country_code ) ) {
			return 'Unknown';
		}
		if ( class_exists('WC_Countries') ) {
			$wc_countries = new \WC_Countries();
			$countries = $wc_countries->get_countries();
			return isset( $countries[ $country_code ] ) ? $countries[ $country_code ] : $country_code;
		} else {
			\ShopMetrics_Logger::get_instance()->info("WC_Countries class not found. Cannot translate country code: " . $country_code);
			return $country_code; 
		}
	}

    /**
     * Get order data for a given order object.
     * Public wrapper for prepare_order_data method.
     *
     * @since    1.1.0
     * @param    \WC_Order $order The order object
     * @return   array|false Order data array or false on failure
     */
    public function get_order_data( $order ) {
        return $this->prepare_order_data( $order );
    }

    /**
     * Synchronizes historical orders in chunks of 100.
     * This method is designed to be triggered via an Action Scheduler hook.
     * It will retrieve orders from the last year, process them in batches, and track progress.
     *
     * @since 1.2.0
     */
    public function sync_historical_orders() {
        \ShopMetrics_Logger::get_instance()->info("Starting historical orders synchronization");
        
        // Check if Action Scheduler is available
        if (!class_exists('ActionScheduler_Store')) {
            \ShopMetrics_Logger::get_instance()->error("ActionScheduler_Store class not found - cannot continue with sync");
            update_option('sm_historical_sync_progress', json_encode([
                'status' => 'error',
                'message' => 'Action Scheduler not available',
                'progress' => 0,
                'timestamp' => time()
            ]));
            return;
        }
        
        // Check if the site is connected
        $api_token = get_option( 'shopmetrics_analytics_api_token', '' );
        if ( empty( $api_token ) ) {
            \ShopMetrics_Logger::get_instance()->error("Cannot sync historical orders - site not connected");
            update_option( 'sm_historical_sync_progress', json_encode([
                'status' => 'error',
                'message' => 'Site not connected',
                'progress' => 0,
                'processed_orders' => 0,
                'total_orders' => 0,
                'timestamp' => time()
            ]));
            return;
        }
        
        // Define the date threshold for historical sync (1 year ago)
        $one_year_ago = gmdate('Y-m-d H:i:s', strtotime('-1 year'));
        \ShopMetrics_Logger::get_instance()->info("Syncing orders from $one_year_ago onwards");
        
        // Get the current progress data
        $progress_data_raw = get_option('sm_historical_sync_progress', '');
        \ShopMetrics_Logger::get_instance()->debug("Current progress data (raw): " . wp_json_encode($progress_data_raw));
        
        // If the progress data is stored as JSON, decode it
        if (is_string($progress_data_raw) && !empty($progress_data_raw) && substr($progress_data_raw, 0, 1) === '{') {
            \ShopMetrics_Logger::get_instance()->debug("Decoding JSON progress data");
            $progress_data = json_decode($progress_data_raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                \ShopMetrics_Logger::get_instance()->error("JSON decode error: " . json_last_error_msg());
                $progress_data = [];
            }
        } else {
            $progress_data = $progress_data_raw;
        }
        
        // Ensure progress data is an array with all required keys
        if (!is_array($progress_data)) {
            \ShopMetrics_Logger::get_instance()->warn("Progress data is not an array, initializing with defaults");
            $progress_data = [];
        }
        
        $progress_data = array_merge([
            'status' => 'starting',
            'progress' => 0,
            'processed_orders' => 0, 
            'total_orders' => 0,
            'last_synced_date' => '',
            'last_synced_id' => 0,
            'timestamp' => time()
        ], $progress_data);
        
        // \ShopMetrics_Logger::get_instance()->info("Using progress data: " . print_r($progress_data, true));
        
        // If this is a new sync, get the total number of orders to process
        if ($progress_data['status'] === 'starting' || $progress_data['total_orders'] === 0) {
            // Clear all sync meta for a fresh sync
            $this->clear_all_sync_meta($one_year_ago);
            
            // Use WooCommerce API instead of direct database query for counting orders
            $cache_key = 'sm_unsynced_orders_count_' . md5($one_year_ago);
            $total_orders = wp_cache_get($cache_key);
            
            if (false === $total_orders) {
                // Count orders using WooCommerce API
                $args = array(
                    'status' => array('completed', 'processing', 'refunded'),
                    'date_created' => '>' . $one_year_ago,
                    'meta_query' => array(
                        array(
                            'key' => '_shopmetrics_synced',
                            'compare' => 'NOT EXISTS'
                        )
                    ),
                    'paginate' => true,
                    'limit' => -1,
                    'return' => 'ids'
                );
                
                $orders_query = wc_get_orders($args);
                $total_orders = is_object($orders_query) ? $orders_query->total : count($orders_query);
                
                // Cache the result for 5 minutes
                wp_cache_set($cache_key, $total_orders, '', 300);
                \ShopMetrics_Logger::get_instance()->info("Counted $total_orders unsynced orders using WooCommerce API");
            } else {
                \ShopMetrics_Logger::get_instance()->info("Using cached count: $total_orders unsynced orders");
            }
            
            $total_orders = intval($total_orders);
            $progress_data['total_orders'] = $total_orders;
            $progress_data['status'] = 'in_progress';
            \ShopMetrics_Logger::get_instance()->info("Found $total_orders unsynced orders to process");
            // Save initial progress data
            $saved = update_option('sm_historical_sync_progress', json_encode($progress_data));
            \ShopMetrics_Logger::get_instance()->debug("Updated initial progress data, result: " . ($saved ? 'true' : 'false'));
        }
        
        // If there are no orders to sync, mark as completed
        if ($progress_data['total_orders'] === 0) {
            \ShopMetrics_Logger::get_instance()->info("No orders found for synchronization");
            $progress_data['status'] = 'completed';
            $progress_data['progress'] = 100;
            $progress_data['processed_orders'] = $progress_data['total_orders'];
            $update_result = update_option('sm_historical_sync_progress', json_encode($progress_data));
            \ShopMetrics_Logger::get_instance()->debug("Marked sync as complete (no orders), update result: " . ($update_result ? 'true' : 'false'));
            return;
        }
        
        // Get the last processed order ID
        $last_processed_id = intval($progress_data['last_synced_id']);
        
        // Get the next batch of orders
        $args = array(
            'status' => array('completed', 'processing', 'refunded'), // Include refunded orders
            'limit' => 20, // Increased batch size from 5 to 20 for faster synchronization
            'date_created' => '>' . $one_year_ago, // Use string format that strtotime can handle
            'orderby' => 'ID',
            'order' => 'ASC',
            'meta_query' => array(
                // Find only orders that haven't been synced yet
                array(
                    'key' => '_shopmetrics_synced',
                    'compare' => 'NOT EXISTS'
                )
            ),
            'type' => 'shop_order' // Explicitly exclude refunds from the query
        );
        
        // If we have a last processed ID, add ID filtering
        if ($last_processed_id > 0) {
            // Post ID greater than filter (proper way to filter by ID in WP_Query)
            $args['post__gt'] = $last_processed_id;
        }
        
        \ShopMetrics_Logger::get_instance()->debug("Query arguments for next batch: " . wp_json_encode($args));
        
        // Get the batch of orders to process
        $orders = wc_get_orders($args);
        \ShopMetrics_Logger::get_instance()->info("Found " . count($orders) . " orders to process in this batch");
        \ShopMetrics_Logger::get_instance()->debug("Memory usage: " . round(memory_get_usage(true) / 1024 / 1024, 2) . "MB / " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . "MB peak");
        
        if (empty($orders)) {
            // No more orders to process, sync is complete
            \ShopMetrics_Logger::get_instance()->info("No more orders to process, marking sync as complete");
            $progress_data['status'] = 'completed';
            $progress_data['progress'] = 100;
            $progress_data['processed_orders'] = $progress_data['total_orders'];
            $progress_data['timestamp'] = time();
            $update_result = update_option('sm_historical_sync_progress', json_encode($progress_data));
            \ShopMetrics_Logger::get_instance()->info("Historical sync completed - processed " . $progress_data['processed_orders'] . " orders, update result: " . ($update_result ? 'true' : 'false'));
            return;
        }
        
        // Prepare batch of orders for bulk submission
        $orders_data = array();
        $processed_ids = array();
        $last_processed_id = intval($progress_data['last_synced_id']); // Initialize from current progress
        $last_processed_date = $progress_data['last_synced_date'] ?? gmdate('Y-m-d');
        
        foreach ($orders as $order) {
            $order_id = $order->get_id();
            
            // Always update last_processed_id to ensure we progress past refunds
            if ($order_id > $last_processed_id) {
                $last_processed_id = $order_id;
                $last_processed_date = $order->get_date_created()->date('Y-m-d');
            }
            
            // Skip refunds but still track their IDs for progression
            if ($order instanceof \WC_Order_Refund) {
                \ShopMetrics_Logger::get_instance()->debug("Skipping refund object ID: " . $order->get_id());
                continue;
            }
            
            \ShopMetrics_Logger::get_instance()->info("Preparing data for order ID: $order_id");
            $order_data = $this->prepare_order_data($order);
            if (!empty($order_data)) {
                $orders_data[] = $order_data;
                $processed_ids[] = $order_id;
                \ShopMetrics_Logger::get_instance()->info("Successfully prepared data for order ID: $order_id");
            } else {
                \ShopMetrics_Logger::get_instance()->info("Failed to prepare data for order ID: $order_id");
            }
        }
        
        // If we have orders to send, submit them in bulk
        if (!empty($orders_data)) {
            try {
                \ShopMetrics_Logger::get_instance()->info("Sending batch of " . count($orders_data) . " orders");
                
                // Check if ShopMetrics_Api_Client and the send_bulk_orders method exist
                \ShopMetrics_Logger::get_instance()->info("Checking if ShopMetrics_Api_Client class exists...");
                if (!class_exists('ShopMetrics\\Analytics\\ShopMetrics_Api_Client')) {
                    \ShopMetrics_Logger::get_instance()->error("ShopMetrics_Api_Client class not found! Loaded classes: " . implode(', ', get_declared_classes()));
                    throw new \Exception("ShopMetrics_Api_Client class not found");
                }
                \ShopMetrics_Logger::get_instance()->info("ShopMetrics_Api_Client class found!");
                
                if (!method_exists('ShopMetrics\\Analytics\\ShopMetrics_Api_Client', 'send_bulk_orders')) {
                    \ShopMetrics_Logger::get_instance()->error("send_bulk_orders method not found in ShopMetrics_Api_Client");
                    throw new \Exception("send_bulk_orders method not found in ShopMetrics_Api_Client");
                }
                \ShopMetrics_Logger::get_instance()->info("send_bulk_orders method found!");
                
                $response = \ShopMetrics\Analytics\ShopMetrics_Api_Client::send_bulk_orders($orders_data);
                
                if (is_wp_error($response)) {
                    \ShopMetrics_Logger::get_instance()->info("Error sending bulk orders - " . $response->get_error_message());
                    $progress_data['last_error'] = $response->get_error_message();
                } else {
                    \ShopMetrics_Logger::get_instance()->info("Successfully sent batch of orders");
                    
                    // Mark these orders as synced
                    foreach ($processed_ids as $synced_id) {
                        $order = wc_get_order($synced_id);
                        if (!$order) {
                            \ShopMetrics_Logger::get_instance()->info("Could not load order $synced_id in meta save loop (Action Scheduler or direct context)");
                        } else {
                            \ShopMetrics_Logger::get_instance()->info("About to mark order ID: $synced_id as synced");
                            $order->update_meta_data('_shopmetrics_synced', 'yes');
                            $order->save();
                            \ShopMetrics_Logger::get_instance()->info("Marked order ID: $synced_id as synced");
                        }
                    }
                    
                    // Update progress data
                    $progress_data['processed_orders'] += count($processed_ids);
                    $progress_data['progress'] = round(($progress_data['processed_orders'] / $progress_data['total_orders']) * 100);
                    $progress_data['last_synced_id'] = $last_processed_id;
                    $progress_data['last_synced_date'] = $last_processed_date ?? gmdate('Y-m-d');
                    $progress_data['timestamp'] = time();
                }
            } catch (\Exception $e) {
                \ShopMetrics_Logger::get_instance()->info("Exception during bulk send - " . $e->getMessage());
                $progress_data['last_error'] = $e->getMessage();
            }
            
            // Save progress
            $update_result = update_option('sm_historical_sync_progress', json_encode($progress_data));
            \ShopMetrics_Logger::get_instance()->info("Updated progress data, result: " . ($update_result ? 'true' : 'false'));
            
            // Check if there are more orders to process
            if ($progress_data['processed_orders'] < $progress_data['total_orders']) {
                \ShopMetrics_Logger::get_instance()->info("[SYNC] processed_orders: {$progress_data['processed_orders']}, total_orders: {$progress_data['total_orders']}");
                \ShopMetrics_Logger::get_instance()->info("[SYNC] Checking for next scheduled action...");
                $already_scheduled = as_next_scheduled_action('shopmetrics_analytics_sync_historical_orders');
                \ShopMetrics_Logger::get_instance()->info("[SYNC] as_next_scheduled_action result: " . wp_json_encode($already_scheduled));
                if (!$already_scheduled) {
                    $next_run = time() + 2;
                    $scheduled = as_schedule_single_action($next_run, 'shopmetrics_analytics_sync_historical_orders');
                    \ShopMetrics_Logger::get_instance()->info("[SYNC] Scheduled next batch: " . wp_json_encode($scheduled) . ", next_run: " . gmdate('Y-m-d H:i:s', $next_run));
                } else {
                    \ShopMetrics_Logger::get_instance()->info("[SYNC] Next batch already scheduled, skipping schedule.");
                }
                return; // Return after scheduling to avoid recursion
            } else {
                // All orders processed, mark as complete
                $progress_data['status'] = 'completed';
                $progress_data['progress'] = 100;
                $progress_data['processed_orders'] = $progress_data['total_orders'];
                $update_result = update_option('sm_historical_sync_progress', json_encode($progress_data));
                \ShopMetrics_Logger::get_instance()->info("Historical sync completed - processed " . $progress_data['processed_orders'] . " orders, update result: " . ($update_result ? 'true' : 'false'));
            }
        } else {
            // No valid orders in this batch, but update progress to move past refunds
            \ShopMetrics_Logger::get_instance()->warn("No valid orders in this batch");
            
            // Debug: Log current vs new values
            \ShopMetrics_Logger::get_instance()->debug("Current last_synced_id: " . $progress_data['last_synced_id'] . ", New last_processed_id: " . $last_processed_id);
            \ShopMetrics_Logger::get_instance()->debug("Current last_synced_date: " . ($progress_data['last_synced_date'] ?? 'null') . ", New last_processed_date: " . $last_processed_date);
            
            // Update progress with the last processed ID to ensure we move forward
            $old_id = $progress_data['last_synced_id'];
            $progress_data['last_synced_id'] = $last_processed_id;
            $progress_data['last_synced_date'] = $last_processed_date;
            $progress_data['timestamp'] = time();
            
            // Force update by adding a unique value if the ID hasn't changed
            if ($old_id == $last_processed_id) {
                $progress_data['force_update'] = time() . '_' . wp_rand(1000, 9999);
                \ShopMetrics_Logger::get_instance()->debug("ID unchanged, adding force_update: " . $progress_data['force_update']);
            }
            
            // Save progress to prevent infinite loops
            $json_data = json_encode($progress_data);
            \ShopMetrics_Logger::get_instance()->debug("Saving progress JSON: " . $json_data);
            $update_result = update_option('sm_historical_sync_progress', $json_data);
            \ShopMetrics_Logger::get_instance()->debug("Updated progress data after skipping refunds, result: " . ($update_result ? 'true' : 'false'));
            
            // Continue processing immediately to move past the refunds
            if ($progress_data['processed_orders'] < $progress_data['total_orders']) {
                \ShopMetrics_Logger::get_instance()->info("Scheduling next batch after skipping refunds");
                // Don't call recursively - use Action Scheduler instead
                if (!as_next_scheduled_action('shopmetrics_analytics_sync_historical_orders')) {
                    $next_run = time() + 1; // Schedule very soon
                    $scheduled = as_schedule_single_action($next_run, 'shopmetrics_analytics_sync_historical_orders');
                    \ShopMetrics_Logger::get_instance()->debug("Scheduled next batch after refunds at " . gmdate('Y-m-d H:i:s', $next_run) . ", scheduled ID: $scheduled");
                } else {
                    \ShopMetrics_Logger::get_instance()->debug("Next batch already scheduled after refunds");
                }
                return;
            }
            
            // If we've processed all orders but the count isn't matching what we expected,
            // recalculate remaining orders to check if we're actually done
            if (empty($orders) && $progress_data['processed_orders'] < $progress_data['total_orders']) {
                // Double-check if there are actually any remaining orders to process
                $remaining_args = array(
                    'status' => array('completed', 'processing', 'refunded'),
                    'limit' => -1,
                    'return' => 'ids',
                    'date_created' => '>' . $one_year_ago,
                    'meta_query' => array(
                        array(
                            'key' => '_shopmetrics_synced',
                            'compare' => 'NOT EXISTS'
                        )
                    )
                );
                
                $remaining_order_ids = wc_get_orders($remaining_args);
                $remaining_real_order_ids = array();
                foreach ($remaining_order_ids as $order_id) {
                    $order = wc_get_order($order_id);
                    if ($order && !($order instanceof \WC_Order_Refund)) {
                        $remaining_real_order_ids[] = $order_id;
                    }
                }
                if (empty($remaining_real_order_ids)) {
                    $progress_data['status'] = 'completed';
                    $progress_data['progress'] = 100;
                    $progress_data['processed_orders'] = $progress_data['total_orders'];
                    $progress_data['timestamp'] = time();
                    update_option('sm_historical_sync_progress', json_encode($progress_data));
                    \ShopMetrics_Logger::get_instance()->info("Historical sync completed - all real orders processed.");
                    return;
                }
                
                // Log IDs of remaining orders to help with debugging
                $remaining_ids = implode(', ', array_slice($remaining_real_order_ids, 0, 5));
                \ShopMetrics_Logger::get_instance()->info("First few remaining order IDs: $remaining_ids");
                
                // Something is preventing us from finding these orders with our normal query
                // Update tracking to reflect actual remaining orders
                $progress_data['total_orders'] = $progress_data['processed_orders'] + count($remaining_real_order_ids);
                $progress_data['progress'] = round(($progress_data['processed_orders'] / $progress_data['total_orders']) * 100);
                
                // Force completing the sync if we're close enough or stuck for too long
                if ($progress_data['progress'] > 95 || 
                    (isset($progress_data['last_update_count']) && 
                     $progress_data['last_update_count'] >= 3)) {
                    \ShopMetrics_Logger::get_instance()->info("Progress over 95% or stuck for too long, marking as completed despite remaining orders.");
                    $progress_data['status'] = 'completed';
                    $progress_data['progress'] = 100;
                } else {
                    // Track repeated calls to this check to identify truly stuck syncs
                    if (!isset($progress_data['last_update_count'])) {
                        $progress_data['last_update_count'] = 1;
                    } else {
                        $progress_data['last_update_count']++;
                    }
                    
                    // Try one more continuation with modified query parameters
                    if ($progress_data['last_update_count'] <= 2) {
                        \ShopMetrics_Logger::get_instance()->info("Attempting continuation with modified query. Attempt #{$progress_data['last_update_count']}");
                        // Save progress first
                        update_option('sm_historical_sync_progress', json_encode($progress_data));
                        
                        // Schedule immediate retry instead of calling recursively
                        if (!as_next_scheduled_action('shopmetrics_analytics_sync_historical_orders')) {
                            $next_run = time() + 1; // Schedule very soon
                            $scheduled = as_schedule_single_action($next_run, 'shopmetrics_analytics_sync_historical_orders');
                            \ShopMetrics_Logger::get_instance()->info("Scheduled retry attempt #{$progress_data['last_update_count']} at " . gmdate('Y-m-d H:i:s', $next_run) . ", scheduled ID: $scheduled");
                        }
                        return;
                    }
                }
                
                $update_result = update_option('sm_historical_sync_progress', json_encode($progress_data));
                \ShopMetrics_Logger::get_instance()->info("Updated final progress after double-check, result: " . ($update_result ? 'true' : 'false'));
                return;
            }
        }
    }

    /**
     * Prepares order data for the API in the same format as send_order_data_to_backend.
     * 
     * @param WC_Order $order The order object to prepare
     * @return array|false Order data array or false on failure
     */
    private function prepare_order_data( $order ) {
        if ( ! $order ) {
            return false;
        }

        $customer_id_from_order = $order->get_customer_id();
        $billing_email = $order->get_billing_email();
        $unique_customer_identifier = '';
        $secret_salt = $this->get_customer_hash_salt(); 

        if ( $customer_id_from_order > 0 ) {
            $unique_customer_identifier = 'user_' . $customer_id_from_order;
        } elseif ( ! empty( $billing_email ) ) {
            $unique_customer_identifier = 'guest_' . hash_hmac('sha256', strtolower(trim($billing_email)), $secret_salt);
        }

        $settings = get_option('shopmetrics_settings', []);
        $user_cogs_meta_key = $settings['cogs_meta_key'] ?? '';
        $default_cogs_percentage = null;
        if (isset($settings['cogs_default_percentage']) && is_numeric($settings['cogs_default_percentage'])) {
            $temp_percentage = floatval($settings['cogs_default_percentage']);
            if ($temp_percentage >= 0 && $temp_percentage <= 100) {
                $default_cogs_percentage = $temp_percentage;
            }
        }
        $known_cogs_fallback_keys = array( '_wc_cog_item_cost', 'cost_of_goods' );
 
        $line_items_data = array();
        $order_items = $order->get_items();

        foreach ( $order_items as $item_id => $item ) {
            /** @var \WC_Order_Item_Product $item */
            $product = $item->get_product();
            $cogs_for_item = null;
            $cogs_reason = '';

            // --- Add categories ---
            $categories = array();
            if ( $product && $product->get_id() ) {
                $terms = get_the_terms( $product->get_id(), 'product_cat' );
                if ( $terms && ! is_wp_error( $terms ) ) {
                    foreach ( $terms as $term ) {
                        $categories[] = $term->name;
                    }
                }
                // Debug log for categories
                \ShopMetrics_Logger::get_instance()->info('Product ID ' . $product->get_id() . ' (' . $product->get_name() . ') categories: ' . json_encode($categories));
            } else {
                \ShopMetrics_Logger::get_instance()->info('No product or invalid product ID for item_id ' . $item_id);
            }
            // --- End categories ---

            // 1. Order item meta (выбранный ключ)
            if ( ! empty( $user_cogs_meta_key ) ) {
                $cogs_value_meta = $item->get_meta( $user_cogs_meta_key, true );
                if ( $cogs_value_meta !== '' && is_numeric( $cogs_value_meta ) && floatval( $cogs_value_meta ) > 0 ) {
                    $cogs_for_item = floatval( $cogs_value_meta );
                    $cogs_reason = 'order_item_meta_key';
                }
            }
            // 2. Fallback-ключи (order item meta)
            if ( is_null( $cogs_for_item ) ) {
                foreach ( $known_cogs_fallback_keys as $f_key ) {
                    $cogs_value_fallback = $item->get_meta( $f_key, true );
                    if ( $cogs_value_fallback !== '' && is_numeric( $cogs_value_fallback ) && floatval( $cogs_value_fallback ) > 0 ) {
                        $cogs_for_item = floatval( $cogs_value_fallback );
                        $cogs_reason = 'order_item_fallback_key:' . $f_key;
                        break;
                    }
                }
            }
            // 3. Default процент
            if ( is_null( $cogs_for_item ) && !is_null( $default_cogs_percentage ) ) {
                $quantity = $item->get_quantity();
                if ( $quantity > 0 ) {
                    $price_per_unit = floatval( $item->get_subtotal() ) / $quantity;
                    $cogs_for_item = $price_per_unit * ( $default_cogs_percentage / 100.0 );
                    $cogs_reason = 'default_percentage';
                }
            }
            // 4. Если ничего не найдено — ставим 0 и логируем
            if ( is_null( $cogs_for_item ) ) {
                $cogs_for_item = 0;
                $cogs_reason = 'not_found';
            }
            // Логируем детали для каждого item
            \ShopMetrics_Logger::get_instance()->info('[ShopMetrics COGS] item_id=' . $item_id . ', product_id=' . $item->get_product_id() . ', meta_key=' . $user_cogs_meta_key . ', default_percent=' . wp_json_encode($default_cogs_percentage) . ', cogs=' . wp_json_encode($cogs_for_item) . ', reason=' . $cogs_reason);
            $line_items_data[] = array(
                'item_id'      => $item_id,
                'product_id'   => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'name'         => $item->get_name(),
                'quantity'     => $item->get_quantity(),
                'subtotal'     => $item->get_subtotal(),
                'total'        => $item->get_total(),
                'sku'          => $product ? $product->get_sku() : null,
                'cogs'         => round(floatval( $cogs_for_item ), 2),
                'categories'   => $categories,
            );
        }

        return array(
            'order_id'                 => $order->get_id(),
            'order_key'                => $order->get_order_key(),
            'status'                   => $order->get_status(),
            'date_created'             => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : null,
            'date_modified'            => $order->get_date_modified() ? $order->get_date_modified()->date( 'c' ) : null,
            'total'                    => $order->get_total(),
            'currency'                 => $order->get_currency(),
            'customer_id'              => $customer_id_from_order, 
            'unique_customer_identifier' => $unique_customer_identifier,
            'customer_ip_address'      => $order->get_customer_ip_address(),
            'customer_user_agent'      => $order->get_customer_user_agent(),
            'payment_method'           => $order->get_payment_method(),
            'payment_method_title'     => $order->get_payment_method_title(),
            'shipping_total'           => $order->get_shipping_total(),
            'shipping_tax'             => $order->get_shipping_tax(),
            'discount_total'           => $order->get_discount_total(),
            'discount_tax'             => $order->get_discount_tax(),
            'total_tax'                => $order->get_total_tax(),
            'subtotal'                 => $order->get_subtotal(),
            'customer_note'            => $order->get_customer_note(),
            'billing_first_name'       => $order->get_billing_first_name(),
            'billing_last_name'        => $order->get_billing_last_name(),
            'billing_company'          => $order->get_billing_company(),
            'billing_address_1'        => $order->get_billing_address_1(),
            'billing_address_2'        => $order->get_billing_address_2(),
            'billing_city'             => $order->get_billing_city(),
            'billing_state'            => $order->get_billing_state(),
            'billing_postcode'         => $order->get_billing_postcode(),
            'billing_country'          => $this->get_country_name_from_code( $order->get_billing_country() ),
            'billing_email'            => $order->get_billing_email(),
            'billing_phone'            => $order->get_billing_phone(),
            'shipping_first_name'      => $order->get_shipping_first_name(),
            'shipping_last_name'       => $order->get_shipping_last_name(),
            'shipping_company'         => $order->get_shipping_company(),
            'shipping_address_1'       => $order->get_shipping_address_1(),
            'shipping_address_2'       => $order->get_shipping_address_2(),
            'shipping_city'            => $order->get_shipping_city(),
            'shipping_state'           => $order->get_shipping_state(),
            'shipping_postcode'        => $order->get_shipping_postcode(),
            'shipping_country'         => $this->get_country_name_from_code( $order->get_shipping_country() ),
            'items'                    => $line_items_data,
            'refunds'                  => $this->get_order_refunds_data( $order ),
        );
    }

    /**
     * Get refund data for an order in the format expected by the backend.
     * 
     * @param WC_Order $order The order object
     * @return array Array of refund data
     */
    private function get_order_refunds_data( $order ) {
        $refunds_data = array();
        
        if ( ! $order ) {
            return $refunds_data;
        }
        
        // Get all refunds for this order
        $refunds = $order->get_refunds();
        
        foreach ( $refunds as $refund ) {
            if ( $refund instanceof \WC_Order_Refund ) {
                $refunds_data[] = array(
                    'refund_id'    => $refund->get_id(),
                    'amount'       => $refund->get_amount(),
                    'reason'       => $refund->get_reason() ? $refund->get_reason() : '',
                    'date_created' => $refund->get_date_created() ? $refund->get_date_created()->date( 'c' ) : null,
                );
            }
        }
        
        \ShopMetrics_Logger::get_instance()->debug("Found " . count($refunds_data) . " refunds for order " . $order->get_id());
        
        return $refunds_data;
    }

    private function clear_all_sync_meta($one_year_ago) {
        // Use WooCommerce API instead of direct database query for clearing meta
        $cache_key = 'sm_orders_to_clear_' . md5($one_year_ago);
        $orders_to_clear = wp_cache_get($cache_key);
        
        if (false === $orders_to_clear) {
            // Get orders using WooCommerce API
            $args = array(
                'status' => array('completed', 'processing'),
                'date_created' => '>' . $one_year_ago,
                'meta_query' => array(
                    array(
                        'key' => '_shopmetrics_synced',
                        'compare' => 'EXISTS'
                    )
                ),
                'limit' => -1,
                'return' => 'ids'
            );
            
            $orders_to_clear = wc_get_orders($args);
            if (is_object($orders_to_clear) && isset($orders_to_clear->orders)) {
                $orders_to_clear = $orders_to_clear->orders;
            }
            
            // Cache the result for 5 minutes
            wp_cache_set($cache_key, $orders_to_clear, '', 300);
        }
        
        $deleted_count = 0;
        if (!empty($orders_to_clear)) {
            foreach ($orders_to_clear as $order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $order->delete_meta_data('_shopmetrics_synced');
                    $order->save();
                    $deleted_count++;
                }
            }
        }
        
        \ShopMetrics_Logger::get_instance()->info("Cleared sync meta for $deleted_count order records using WooCommerce API");
    }
}

// --- Auto-kick sync_historical_orders on frontend hits if sync is in progress ---
add_action('init', function() {
    // Только для не-админки и не AJAX
    if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
        return;
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }

    // Проверяем прогресс sync
    $progress_data = get_option('sm_historical_sync_progress', '');
    $progress = !empty($progress_data) ? json_decode($progress_data, true) : null;

    // Если sync не идет — ничего не делаем
    if (!$progress || !in_array($progress['status'], ['in_progress', 'starting'])) {
        return;
    }

    // Throttle (раз в 15 сек)
    if (get_transient('shopmetrics_sync_throttle')) {
        return;
    }
    set_transient('shopmetrics_sync_throttle', 1, 15);

    if (function_exists('as_next_scheduled_action')) {
        $next_run = as_next_scheduled_action('shopmetrics_analytics_sync_historical_orders');
        if (!$next_run || (isset($progress['timestamp']) && (time() - intval($progress['timestamp']) > 15))) {
            do_action('shopmetrics_analytics_sync_historical_orders');
        }
    }
});
