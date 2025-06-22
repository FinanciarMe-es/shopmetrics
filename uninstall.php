<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * This script ensures all plugin data is properly cleaned up when the plugin
 * is deleted from the WordPress admin interface.
 *
 * @since      1.0.0
 * @package    Financiarme_Analytics
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Notify backend that site is being disconnected
 */
function shopmetrics_notify_disconnect() {
    // Get site credentials before deleting them
    $api_token = get_option('shopmetrics_analytics_api_token');
    $site_identifier = get_option('shopmetrics_analytics_site_identifier');
    
    // Only proceed if we have the necessary credentials
    if (!$api_token || !$site_identifier) {
        return;
    }

    // API endpoint
    $api_url = 'https://api.financiar.me/v1/disconnect-site';
    
    // Prepare headers
    $headers = array(
        'Content-Type' => 'application/json',
        'X-FinanciarMe-Site-Identifier' => $site_identifier,
        'X-FinanciarMe-Token' => $api_token,
    );

    // Make the request to notify backend about disconnection
    $response = wp_remote_post($api_url, array(
        'method'      => 'POST',
        'timeout'     => 30,
        'headers'     => $headers,
        'body'        => json_encode(array(
            'uninstall' => true,
            'reason' => 'plugin_uninstall',
            'status' => 'inactive'
        )),
        'sslverify'   => true
    ));

    // Log the result if debugging is enabled
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        if (is_wp_error($response)) {
            // \ShopMetrics_Logger::get_instance()->info('ShopMetrics: Failed to notify backend about disconnect: ' . $response->get_error_message());
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code === 200) {
                // \ShopMetrics_Logger::get_instance()->info('ShopMetrics: Successfully notified backend about disconnect');
            } else {
                // \ShopMetrics_Logger::get_instance()->info('ShopMetrics: Backend disconnect notification returned: ' . $response_code);
            }
        }
    }
}

// Notify backend before deleting credentials
shopmetrics_notify_disconnect();

// Delete all plugin options and settings
$options_to_delete = array(
    'shopmetrics_analytics_api_token',
    'shopmetrics_analytics_site_identifier',
    'shopmetrics_settings',
    'shopmetrics_needs_onboarding',
    'shopmetrics_subscription_status',
    'shopmetrics_cancel_at',
    'shopmetrics_analytics_cogs_meta_key',
    'shopmetrics_analytics_cogs_default_percentage',
    'shopmetrics_analytics_enable_visit_tracking',
    'shopmetrics_analytics_enable_low_stock_notifications',
    'shopmetrics_analytics_low_stock_notification_recipients',
    'shopmetrics_selected_order_blocks',
    'shopmetrics_last_sync',
    'shopmetrics_activation_redirect',
    'shopmetrics_analytics_cart_recovery_enabled',
    'shopmetrics_analytics_recovery_email_template',
    'shopmetrics_analytics_recovery_email_subject',
    'shopmetrics_analytics_recovery_hours',
);

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// If using transients, optionally delete those as well
delete_transient('shopmetrics_activation_redirect');
delete_transient('shopmetrics_analytics_last_sync_time');

// Remove scheduled cron events if any
$cron_hooks = array(
    'shopmetrics_daily_data_sync',
    'shopmetrics_snapshot_action',
    'shopmetrics_analytics_take_inventory_snapshot',
    'shopmetrics_analytics_send_recovery_emails',
    'shopmetrics_analytics_process_cart_recovery'
);

foreach ($cron_hooks as $hook) {
    $timestamp = wp_next_scheduled($hook);
    if ($timestamp) {
        wp_unschedule_event($timestamp, $hook);
    }
}

// Check for Action Scheduler events and remove them
if (function_exists('as_unschedule_all_actions')) {
    as_unschedule_all_actions('shopmetrics_analytics_do_historical_sync');
}

// Optionally log that uninstall was successful
if (defined('WP_DEBUG') && WP_DEBUG === true) {
    // \ShopMetrics_Logger::get_instance()->info('Plugin uninstalled and all options deleted.');
} 