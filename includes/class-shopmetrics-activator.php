<?php

namespace ShopMetrics\Analytics;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    ShopMetrics_Analytics
 * @subpackage ShopMetrics_Analytics/includes
 * @author     ShopMetrics <info@shopmetrics.com>
 */
class ShopMetrics_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		// Set a transient flag to trigger redirect on next admin page load
		set_transient( 'shopmetrics_activation_redirect', true, 30 ); // Expire after 30 seconds
		
		// Set the onboarding flag if the plugin is not already configured
		$api_token = get_option('shopmetrics_analytics_api_token');
		$site_identifier = get_option('shopmetrics_analytics_site_identifier');
		
		if (empty($api_token) || empty($site_identifier)) {
			// Plugin is not configured, set the onboarding flag
			update_option('shopmetrics_needs_onboarding', 'true');
			\ShopMetrics_Logger::get_instance()->info('Setting onboarding flag to true');
			
			// Set default subscription status to 'free' for new installs
			// This prevents "No Active Subscription" showing before API connection
			update_option('shopmetrics_subscription_status', 'free');
			\ShopMetrics_Logger::get_instance()->info('Setting default subscription status to free for new install');
		} else {
			\ShopMetrics_Logger::get_instance()->info('API token and site identifier already set, no need for onboarding');
		}
		
		// Ensure the snapshotter class is available.
		$snapshotter_file = plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-shopmetrics-snapshotter.php';
		if ( file_exists( $snapshotter_file ) ) {
			require_once $snapshotter_file;
			if ( class_exists( '\ShopMetrics_Snapshotter' ) && method_exists( '\ShopMetrics_Snapshotter', 'schedule_snapshot_action' ) ) {
				\ShopMetrics_Snapshotter::schedule_snapshot_action();
				\ShopMetrics_Logger::get_instance()->info('Snapshot action scheduling attempted on activation.');
			} else {
				\ShopMetrics_Logger::get_instance()->warn('ShopMetrics_Snapshotter class or schedule_snapshot_action method not found.');
			}
		} else {
			\ShopMetrics_Logger::get_instance()->warn('Snapshotter class file not found at ' . $snapshotter_file);
		}
	}

} 