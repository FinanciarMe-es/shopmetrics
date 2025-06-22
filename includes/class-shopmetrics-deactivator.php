<?php

namespace ShopMetrics\Analytics;

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    ShopMetrics_Analytics
 * @subpackage ShopMetrics_Analytics/includes
 * @author     ShopMetrics <info@shopmetrics.com>
 */
class ShopMetrics_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
        // Unschedule the inventory snapshot action.
        if ( function_exists( 'as_unschedule_all_actions' ) ) {
            // Ensure the snapshotter class is available to get the hook constant.
            $snapshotter_file = plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-shopmetrics-snapshotter.php';
            if ( file_exists( $snapshotter_file ) ) {
                require_once $snapshotter_file;
                if ( defined( 'ShopMetrics_Snapshotter::SNAPSHOT_HOOK' ) ) {
                    as_unschedule_all_actions( \ShopMetrics_Snapshotter::SNAPSHOT_HOOK );
                    \ShopMetrics_Logger::get_instance()->info("Unscheduled all actions for hook: " . \ShopMetrics_Snapshotter::SNAPSHOT_HOOK);
                } else {
                    \ShopMetrics_Logger::get_instance()->info("ShopMetrics Analytics Deactivator: SNAPSHOT_HOOK constant not defined in ShopMetrics_Snapshotter.");
                }
            } else {
                \ShopMetrics_Logger::get_instance()->info("ShopMetrics Analytics Deactivator: Snapshotter class file not found. Cannot unschedule specific hook.");
                // Fallback: If we can't get the specific hook, we might not unschedule or unschedule all by group if one was used.
                // For now, we log and do nothing further if the specific hook isn't available.
            }
        } else {
            \ShopMetrics_Logger::get_instance()->info("ShopMetrics Analytics Deactivator: Action Scheduler function as_unschedule_all_actions() not found.");
        }

        // Trigger site disconnection action for webhook removal
        do_action('shopmetrics_analytics_deactivated');
        \ShopMetrics_Logger::get_instance()->info("Triggered deactivation action for webhook removal");

        // TODO: Add other deactivation logic if needed (e.g., remove options, clear transients)
        // Example: Remove the redirect transient if it exists
        if ( get_transient( 'shopmetrics_activation_redirect' ) ) {
            delete_transient( 'shopmetrics_activation_redirect' );
        }
	}

} 