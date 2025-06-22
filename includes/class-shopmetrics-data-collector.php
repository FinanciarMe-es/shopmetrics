<?php

namespace ShopMetrics\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Handles historical data synchronization.
 *
 * @since      1.0.0
 * @package    Financiarme_Analytics
 * @subpackage ShopMetrics/includes
 * @author     FinanciarMe <info@financiarme.es>
 */
class ShopMetrics_Data_Collector {

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
	 * Initialize hooks.
	 *
	 * @since    1.0.0
	 */
    public function init_hooks() {
        // Hooks for order events (new, status change, refund) have been moved to Orders_Tracker class.
        // This class is now primarily for historical data sync and potentially other general data tasks.
    }
    
    /**
     * Initialize hooks for background actions.
     *
     * Separate from init_hooks() which runs on every page load.
     * This hooks the function that Action Scheduler will execute.
     * 
     * @since 1.0.0
     */
    public function init_async_hooks() {
        // Hook for the historical sync job
        add_action( 'shopmetrics_analytics_do_historical_sync', array( $this, 'perform_historical_sync' ) );
        
        // Hooks for sending order data, status updates, and refund data have been moved to Orders_Tracker class.
    }

    /**
     * Performs the historical data synchronization.
     *
     * This function is executed by Action Scheduler in the background.
     *
     * @since 1.0.0
     */
    public function perform_historical_sync() {
        		\ShopMetrics_Logger::get_instance()->info("Starting historical data sync...");
        
        // TODO: Implement robust error handling and progress tracking
        // TODO: Set a transient/option to indicate sync is running/completed/failed

        // --- Sync Orders --- 
        $order_page = 1;
        $orders_processed = 0;
        $batch_size = 50; // Process orders in batches to avoid memory issues

        $orders_tracker = null;
        if (isset($this->plugin->orders_tracker) && $this->plugin->orders_tracker instanceof \ShopMetrics\Analytics\ShopMetrics_Orders_Tracker) {
            $orders_tracker = $this->plugin->orders_tracker;
        } else {
            			\ShopMetrics_Logger::get_instance()->error("Orders_Tracker instance not available via main plugin reference. Cannot process historical orders.");
            return; // Exit if the Orders_Tracker isn't available
        }

        do {
            $orders = wc_get_orders( array(
                'limit'    => $batch_size,
                'paged'    => $order_page,
                'orderby'  => 'date',
                'order'    => 'ASC',
                'return'   => 'ids', // Get only IDs for efficiency
            ) );

            if ( ! empty( $orders ) ) {
                foreach ( $orders as $order_id ) {
                    // Call the handle_new_order method from the Orders_Tracker instance
                    if ($orders_tracker) {
                        // handle_new_order in Orders_Tracker will schedule an async action
                        // to send the order data.
                        $orders_tracker->handle_new_order( $order_id ); 
                    } else {
                        // This case should ideally not be reached if the initial check passed and $orders_tracker was set.
                        				\ShopMetrics_Logger::get_instance()->error("Orders_Tracker instance became unavailable during loop for order $order_id in historical sync. This indicates an issue.");
                    }
                    $orders_processed++;
                }
                $order_page++;
            } else {
                break; // No more orders found
            }
            
            // Optional: Add a small delay or check memory usage between batches
            // sleep(1);

        } while ( count( $orders ) === $batch_size ); // Continue if we likely got a full batch

        \ShopMetrics_Logger::get_instance()->info("Finished historical order sync. Processed: " . $orders_processed . " orders.");

        // TODO: Sync Customers (if needed)
        // TODO: Sync Products (if needed, e.g., for initial COGS/category mapping)

        // TODO: Update transient/option to indicate sync completion
    }

}