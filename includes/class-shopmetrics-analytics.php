<?php
/**
 * Analytics functionality for ShopMetrics
 *
 * Handles analytics settings and frontend integration
 * PostHog analytics now runs frontend-only for WordPress.org compatibility
 *
 * @link       https://shopmetrics.es
 * @since      1.3.0
 *
 * @package    ShopMetrics
 * @subpackage ShopMetrics/includes
 */

namespace ShopMetrics\Analytics;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Analytics now handled frontend-only for WordPress.org compatibility
// All PostHog integration moved to React components

use WP_Error;

\ShopMetrics_Logger::get_instance()->debug('ShopMetrics Analytics Debug: Analytics class loading (frontend-only mode)');

/**
 * Analytics functionality for ShopMetrics
 *
 * Handles analytics settings and frontend integration with PostHog JS SDK
 * All actual analytics tracking happens in the React frontend
 */
class ShopMetrics_Analytics {
    
    private static $instance = null;
    
    /**
     * Track if analytics has been initialized
     */
    private static $initialized = false;
    
    /**
     * Analytics now handled frontend-only
     */
    
    /**
     * Log debug message
     */
    private static function log($message, $level = 'INFO') {
        $logger = \ShopMetrics_Logger::get_instance();
        switch (strtoupper($level)) {
            case 'ERROR':
                $logger->error($message);
                break;
            case 'DEBUG':
                $logger->debug($message);
                break;
            default:
                $logger->log($message, \ShopMetrics_Logger::INFO);
        }
    }
    
    /**
     * Check if analytics are enabled (user consent + settings)
     */
    public static function is_enabled() {
        $settings = get_option('shopmetrics_settings', []);
        $consent = !empty($settings['analytics_consent']);
        $enabled = $consent && defined('SHOPMETRICS_VERSION');
        
        return $enabled;
    }
    
    /**
     * Check if tracking is enabled (frontend handles actual tracking)
     */
    public static function is_tracking_enabled() {
        return self::is_enabled();
    }
    
    /**
     * Initialize analytics (frontend-only mode)
     */
    public static function init() {
        // Use transient for more reliable initialization tracking
        $init_key = 'shopmetrics_analytics_init_' . get_current_blog_id();
        $last_init = get_transient($init_key);
        
        // Only allow initialization once per hour to prevent spam
        if ($last_init && (time() - $last_init) < 3600) {
            return;
        }
        
        // Prevent multiple initializations within the same request
        if (self::$initialized) {
            return;
        }
        
        self::log("Analytics init started (frontend-only mode), is_admin=" . (is_admin() ? 'yes' : 'no'));
        
        if (!is_admin()) {
            self::log("Not admin area, skipping analytics init");
            return;
        }
        
        // Mark as initialized for this request
        self::$initialized = true;
        
        // Set transient to prevent re-initialization for 1 hour
        set_transient($init_key, time(), 3600);
        
        self::log("Analytics enabled: " . (self::is_enabled() ? 'yes' : 'no'));
        
        // Set up hooks only once
        add_action('admin_init', [__CLASS__, 'register_settings']);
        
        // Enqueue analytics script for frontend integration
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_analytics_script']);
        
        self::log("Analytics initialization completed (frontend-only mode)");
    }
    
    /**
     * PostHog now handled in frontend - this method kept for compatibility
     */
    public static function init_posthog() {
        self::log("PostHog initialization skipped - handled in frontend");
        return true;
    }
    
    /**
     * Handle analytics errors (kept for compatibility)
     */
    public static function handle_posthog_error($error) {
        self::log("Analytics error (frontend will handle): " . $error, 'ERROR');
    }
    
    /**
     * Register settings for analytics consent
     */
    public static function register_settings() {
        // Analytics settings are handled by main admin class
        // This method kept for compatibility
    }
    
    /**
     * Enqueue analytics script for frontend integration
     */
    public static function enqueue_analytics_script($hook_suffix) {
        // Only load on ShopMetrics pages
        if (strpos($hook_suffix, 'shopmetrics') === false) {
            return;
        }
        
        // Pass analytics configuration to frontend
        $analytics_config = self::get_config();
        wp_localize_script('shopmetrics-admin', 'shopmetricsAnalytics', $analytics_config);
        
        self::log("Analytics config passed to frontend");
    }
    
    /**
     * Get site hash for analytics
     */
    public static function get_site_hash() {
        $site_url = get_site_url();
        return substr(md5($site_url), 0, 8);
    }
    
    /**
     * Get analytics configuration for frontend
     */
    public static function get_config() {
        $settings = get_option('shopmetrics_settings', []);
        
        return [
            'enabled' => self::is_enabled(),
            'consent' => !empty($settings['analytics_consent']),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'site_hash' => self::get_site_hash(),
            'user_properties' => self::get_user_properties()
        ];
    }
    
    /**
     * Get user properties for analytics
     */
    private static function get_user_properties() {
        global $wp_version;
        
        $wc_version = '';
        if (class_exists('WooCommerce')) {
            $wc_version = WC()->version;
        }
        
        $current_user = wp_get_current_user();
        $settings = get_option('shopmetrics_settings', []);
        
        return [
            'site_url' => get_site_url(),
            'wp_version' => $wp_version,
            'wc_version' => $wc_version,
            'plugin_version' => defined('SHOPMETRICS_VERSION') ? SHOPMETRICS_VERSION : '1.0.0',
            'user_role' => !empty($current_user->roles) ? $current_user->roles[0] : 'unknown',
            'subscription_plan' => $settings['subscription_plan'] ?? 'free',
            'site_hash' => self::get_site_hash()
        ];
    }
    
    /**
     * Frontend-only tracking methods (kept for compatibility)
     * All actual tracking now happens in React components
     */
    public static function track_event($event_name, $properties = array()) {
        self::log("Event tracking moved to frontend: " . $event_name);
        return true;
    }
    
    public static function track_page_view() {
        self::log("Page view tracking moved to frontend");
        return true;
    }
    
    public static function track_subscription_event($action, $plan = null) {
        self::log("Subscription event tracking moved to frontend: " . $action);
        return true;
    }
    
    public static function track_onboarding_event($step, $action, $data = []) {
        self::log("Onboarding event tracking moved to frontend: " . $step . " - " . $action);
        return true;
    }
    
    public static function track_button_click($button_type, $location, $additional_data = []) {
        self::log("Button click tracking moved to frontend: " . $button_type);
        return true;
    }
    
    public static function track_feature_usage($feature, $action, $data = []) {
        self::log("Feature usage tracking moved to frontend: " . $feature);
        return true;
    }
    
    /**
     * Debug and testing methods (simplified for frontend-only mode)
     */
    public static function get_debug_log() {
        return "Analytics debug moved to frontend. Check browser console for analytics logs.";
    }
    
    public static function get_log_file_path() {
        return null; // No longer needed - frontend handles logging
    }
    
    public static function clear_debug_log() {
        self::log("Debug log clearing moved to frontend");
        return true;
    }
    
    /**
     * Test page rendering (simplified)
     */
    public static function render_test_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Analytics Test (Frontend Mode)', 'shopmetrics'); ?></h1>
            <div class="notice notice-info">
                <p><?php _e('Analytics testing is now handled in the frontend React application. Use the browser console to see analytics events.', 'shopmetrics'); ?></p>
            </div>
            
            <div class="card">
                <h2><?php _e('Frontend Analytics Status', 'shopmetrics'); ?></h2>
                <p><strong><?php _e('Enabled:', 'shopmetrics'); ?></strong> <?php echo self::is_enabled() ? __('Yes', 'shopmetrics') : __('No', 'shopmetrics'); ?></p>
                <p><strong><?php _e('Mode:', 'shopmetrics'); ?></strong> <?php _e('Frontend-only (PostHog JS SDK)', 'shopmetrics'); ?></p>
                <p><strong><?php _e('Site Hash:', 'shopmetrics'); ?></strong> <?php echo esc_html(self::get_site_hash()); ?></p>
            </div>
            
            <div class="card">
                <h2><?php _e('Testing Instructions', 'shopmetrics'); ?></h2>
                <ol>
                    <li><?php _e('Open browser developer tools (F12)', 'shopmetrics'); ?></li>
                    <li><?php _e('Go to Console tab', 'shopmetrics'); ?></li>
                    <li><?php _e('Navigate through the ShopMetrics interface', 'shopmetrics'); ?></li>
                    <li><?php _e('Look for "[ShopMetrics Analytics]" log messages', 'shopmetrics'); ?></li>
                </ol>
            </div>
        </div>
        <?php
    }
    
    /**
     * Simplified test methods (frontend handles actual testing)
     */
    public static function send_test_event() {
        self::log("Test event sending moved to frontend");
        return ['success' => true, 'message' => 'Test events now handled in frontend'];
    }
    
    public static function send_test_error() {
        self::log("Test error sending moved to frontend");
        return ['success' => true, 'message' => 'Error testing now handled in frontend'];
    }
    
    public static function test_error_scenarios() {
        self::log("Error scenario testing moved to frontend");
        return ['success' => true, 'message' => 'Error scenarios now tested in frontend'];
    }
    
    public static function simulate_fatal_error() {
        self::log("Fatal error simulation moved to frontend");
        return ['success' => true, 'message' => 'Fatal error simulation now handled in frontend'];
    }
    
    /**
     * Reset initialization (kept for compatibility)
     */
    public static function reset_initialization() {
        self::$initialized = false;
        $init_key = 'shopmetrics_analytics_init_' . get_current_blog_id();
        delete_transient($init_key);
        self::log("Analytics initialization reset");
    }
    
    /**
     * Error tracking methods (simplified - frontend handles actual tracking)
     */
    public static function track_error($error_type, $message, $file = null, $line = null, $trace = null, $context = []) {
        self::log("Error tracking moved to frontend: " . $error_type . " - " . $message);
        return true;
    }
    
    public static function track_exception($exception, $context = []) {
        self::log("Exception tracking moved to frontend: " . $exception->getMessage());
        return true;
    }
    
    /**
     * Error handlers removed - frontend handles error tracking
     */
    public static function register_error_handlers() {
        self::log("Error handlers registration skipped - handled in frontend");
    }
    
    public static function handle_php_error($severity, $message, $file, $line) {
        // No longer needed - frontend handles error tracking
        return false;
    }
    
    public static function handle_uncaught_exception($exception) {
        // No longer needed - frontend handles error tracking
    }
    
    public static function handle_fatal_error() {
        // No longer needed - frontend handles error tracking
    }
    
    /**
     * AJAX handler for saving analytics consent
     */
    public static function ajax_save_analytics_consent() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'shopmetrics_admin_nonce')) {
            wp_die(__('Security check failed', 'shopmetrics'));
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'shopmetrics'));
        }
        
        $consent = isset($_POST['consent']) && $_POST['consent'] === 'true';
        
        // Save consent to settings
        $settings = get_option('shopmetrics_settings', []);
        $settings['analytics_consent'] = $consent;
        update_option('shopmetrics_settings', $settings);
        
        self::log("Analytics consent updated: " . ($consent ? 'granted' : 'withdrawn'));
        
        wp_send_json_success([
            'consent' => $consent,
            'message' => $consent ? 
                __('Analytics consent granted', 'shopmetrics') : 
                __('Analytics consent withdrawn', 'shopmetrics')
        ]);
    }
} 