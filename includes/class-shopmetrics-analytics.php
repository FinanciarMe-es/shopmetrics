<?php
/**
 * Analytics integration with PostHog using PHP SDK
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

// Debug: Log before attempting to load PostHog
\ShopMetrics_Logger::get_instance()->debug('ShopMetrics Analytics Debug: Starting to load PostHog library');
$vendor_path = plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php';
\ShopMetrics_Logger::get_instance()->debug('ShopMetrics Analytics Debug: Vendor path: ' . $vendor_path);
\ShopMetrics_Logger::get_instance()->debug('ShopMetrics Analytics Debug: Vendor file exists: ' . (file_exists($vendor_path) ? 'yes' : 'no'));

// Load PostHog PHP library
try {
    require_once $vendor_path;
    \ShopMetrics_Logger::get_instance()->debug('ShopMetrics Analytics Debug: Vendor autoload included successfully');
} catch (Exception $e) {
    \ShopMetrics_Logger::get_instance()->error('ShopMetrics Analytics Debug: Error loading vendor autoload: ' . $e->getMessage());
} catch (Error $e) {
    \ShopMetrics_Logger::get_instance()->error('ShopMetrics Analytics Debug: Fatal error loading vendor autoload: ' . $e->getMessage());
}

use PostHog\PostHog;

\ShopMetrics_Logger::get_instance()->debug('ShopMetrics Analytics Debug: About to define class ShopMetrics_Analytics');

/**
 * Analytics functionality for ShopMetrics
 *
 * Handles PostHog integration with privacy-first approach using PHP SDK
 * All data collection requires explicit user consent (GDPR compliant)
 */
class ShopMetrics_Analytics {
    
    /**
     * PostHog project API key
     */
    const POSTHOG_API_KEY = 'phc_AWlR9ugS9UZQAN2VehoFv5oRWpOICmYcHkTnuBmGGdI';
    
    /**
     * PostHog API host (EU for GDPR compliance)
     */
    const POSTHOG_HOST = 'https://eu.i.posthog.com';
    
    /**
     * Debug log file path
     */
    private static $log_file = null;
    
    /**
     * Track if analytics has been initialized
     */
    private static $initialized = false;
    
    /**
     * Track if PostHog has been initialized
     */
    private static $posthog_initialized = false;
    
    /**
     * Get log file path
     */
    private static function get_log_file() {
        if (self::$log_file === null) {
            // Initialize WordPress filesystem
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }
            
            // Try WordPress uploads directory first
            if (function_exists('wp_upload_dir')) {
                $upload_dir = wp_upload_dir();
                if (!empty($upload_dir['basedir']) && $wp_filesystem->is_dir($upload_dir['basedir']) && $wp_filesystem->is_writable($upload_dir['basedir'])) {
                    self::$log_file = $upload_dir['basedir'] . '/shopmetrics-analytics.log';
                }
            }
            
            // Fallback to specific WordPress uploads path
            if (self::$log_file === null) {
                $fallback_dir = '/Users/alexander/Local Sites/financiarme-dev/app/public/wp-content/uploads';
                if ($wp_filesystem->is_dir($fallback_dir) && $wp_filesystem->is_writable($fallback_dir)) {
                    self::$log_file = $fallback_dir . '/shopmetrics-analytics.log';
                }
            }
            
            // Final fallback to plugin directory
            if (self::$log_file === null) {
                $plugin_dir = plugin_dir_path(dirname(__FILE__));
                if ($wp_filesystem->is_writable($plugin_dir)) {
                    self::$log_file = $plugin_dir . 'shopmetrics-analytics.log';
                }
            }
            
            // Last resort - temp directory
            if (self::$log_file === null) {
                self::$log_file = sys_get_temp_dir() . '/shopmetrics-analytics.log';
            }
        }
        return self::$log_file;
    }
    
    /**
     * Log debug message
     */
    private static function log($message, $level = 'INFO') {
        // Используем ShopMetrics_Logger для логирования
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
        
        // self::log("Analytics enabled check: consent={$consent}, version_defined=" . (defined('SHOPMETRICS_VERSION') ? 'yes' : 'no') . ", result={$enabled}");
        
        return $enabled;
    }
    
    /**
     * Initialize analytics
     */
    public static function init() {
        // Use transient for more reliable initialization tracking
        $init_key = 'shopmetrics_analytics_init_' . get_current_blog_id();
        $last_init = get_transient($init_key);
        
        // Only allow initialization once per hour to prevent spam
        if ($last_init && (time() - $last_init) < 3600) {
            // Don't log anything to avoid spam
            return;
        }
        
        // Prevent multiple initializations within the same request
        if (self::$initialized) {
            return;
        }
        
        self::log("Analytics init started, is_admin=" . (is_admin() ? 'yes' : 'no'));
        
        if (!is_admin()) {
            self::log("Not admin area, skipping analytics init");
            return;
        }
        
        // Mark as initialized for this request
        self::$initialized = true;
        
        // Set transient to prevent re-initialization for 1 hour
        set_transient($init_key, time(), 3600);
        
        // Initialize PostHog PHP client
        if (self::is_enabled()) {
            self::log("Analytics enabled, initializing PostHog client");
            self::init_posthog();
        } else {
            self::log("Analytics disabled, skipping PostHog init");
        }
        
        // Set up hooks only once
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('current_screen', [__CLASS__, 'track_page_view']);
        
        // Still load minimal JS for client-side events
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_analytics_script']);
        
        // Register error handlers for comprehensive error tracking
        self::register_error_handlers();
        
        // Remove AJAX handlers - frontend now talks directly to PostHog
        // add_action('wp_ajax_shopmetrics_track_event', [__CLASS__, 'handle_ajax_event']);
        // add_action('wp_ajax_nopriv_shopmetrics_track_event', [__CLASS__, 'handle_ajax_event']);
        
        self::log("Analytics initialization completed");
    }
    
    /**
     * Initialize PostHog PHP client
     */
    public static function init_posthog() {
        // Use transient for PostHog initialization tracking
        $posthog_key = 'shopmetrics_posthog_init_' . get_current_blog_id();
        $last_posthog_init = get_transient($posthog_key);
        
        self::log("init_posthog() called - transient: {$last_posthog_init}, initialized: " . (self::$posthog_initialized ? 'true' : 'false'));
        
        // Only check transient if we think we're already initialized
        if (self::$posthog_initialized && $last_posthog_init && (time() - $last_posthog_init) < 300) {
            self::log("Skipping PostHog init - initialized " . (time() - $last_posthog_init) . " seconds ago");
            return;
        }
        
        self::log("Initializing PostHog with API key: " . substr(self::POSTHOG_API_KEY, 0, 10) . "... and host: " . self::POSTHOG_HOST);
        
        try {
            // Initialize PostHog first
            PostHog::init(self::POSTHOG_API_KEY, [
                'host' => self::POSTHOG_HOST,
                'debug' => false, // Disable debug to reduce noise
                'timeout' => 30,
                'ssl_verify' => true
            ]);
            
            // Mark as initialized immediately after PostHog::init()
            self::$posthog_initialized = true;
            set_transient($posthog_key, time(), 300);
            self::log("PostHog initialized successfully");
            
        } catch (Exception $e) {
            self::log("PostHog init error: " . $e->getMessage(), 'ERROR');
            self::log("PostHog init stack trace: " . $e->getTraceAsString(), 'ERROR');
            self::$posthog_initialized = false;
        }
    }
    
    /**
     * Handle PostHog errors
     */
    public static function handle_posthog_error($error) {
        self::log("PostHog error callback: " . $error, 'ERROR');
    }
    
    /**
     * Register analytics settings
     */
    public static function register_settings() {
        // No need to register shopmetrics_analytics_consent
    }
    
    /**
     * Enqueue minimal analytics JavaScript for client-side events
     */
    public static function enqueue_analytics_script($hook_suffix) {
        // Only log for ShopMetrics pages to reduce spam
        $sm_test_analytics = isset($_GET['sm_test_analytics']) ? sanitize_text_field(wp_unslash($_GET['sm_test_analytics'])) : '';
        if (strpos($hook_suffix, 'shopmetrics') === false && empty($sm_test_analytics)) {
            return;
        }
        
        self::log("Enqueuing analytics script for: {$hook_suffix}");
        
        wp_enqueue_script(
            'shopmetrics-analytics',
            plugin_dir_url(dirname(__FILE__)) . 'admin/js/shopmetrics-analytics-php.js',
            ['jquery'],
            SHOPMETRICS_VERSION,
            true
        );
        
        // Simplified analytics configuration - only PostHog direct connection
        wp_localize_script('shopmetrics-analytics', 'shopmetricsAnalytics', [
            'enabled' => self::is_enabled(),
            'siteHash' => self::get_site_hash(),
            'pluginVersion' => SHOPMETRICS_VERSION,
            'posthogKey' => self::POSTHOG_API_KEY,
            'posthogHost' => self::POSTHOG_HOST,
            'nonce' => wp_create_nonce('shopmetrics_analytics_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'sessionRecording' => [
                'enabled' => true,
                'maskAllInputs' => true, // Privacy-first: mask all inputs
                'maskAllText' => false,  // Allow text recording for UX insights
                'recordCanvas' => false, // Disable canvas recording for performance
                'recordCrossOriginIframes' => false
            ]
        ]);
        
        self::log("Analytics script enqueued successfully - direct PostHog mode");
    }
    
    /**
     * Get anonymous site hash for identification
     */
    public static function get_site_hash() {
        // Cache the hash to avoid recalculating frequently
        static $cached_hash = null;
        if ($cached_hash === null) {
            $cached_hash = hash('sha256', site_url() . wp_salt('auth'));
        }
        return $cached_hash;
    }
    
    /**
     * Get analytics configuration for client-side scripts
     */
    public static function get_config() {
        $config = [
            'posthog_key' => self::POSTHOG_API_KEY,
            'posthog_host' => self::POSTHOG_HOST,
            'site_hash' => self::get_site_hash(),
            'plugin_version' => SHOPMETRICS_VERSION,
            'enabled' => self::is_enabled(),
            'nonce' => wp_create_nonce('shopmetrics_analytics_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php')
        ];
        
        self::log("get_config() returning: " . json_encode($config));
        
        return $config;
    }
    
    /**
     * Get user properties for analytics
     */
    private static function get_user_properties() {
        $current_user = wp_get_current_user();
        
        $properties = [
            'site_hash' => self::get_site_hash(),
            'site_domain' => wp_parse_url(site_url(), PHP_URL_HOST),
            'plugin_version' => SHOPMETRICS_VERSION,
            'wp_version' => get_bloginfo('version'),
            'subscription_plan' => get_option('shopmetrics_subscription_plan', 'free'),
            'user_role' => $current_user->roles[0] ?? 'unknown',
            'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : 'not_installed',
            'php_version' => PHP_VERSION,
            'locale' => get_locale(),
            'timezone' => wp_timezone_string(),
            'multisite' => is_multisite() ? 'yes' : 'no',
            'source' => 'server'
        ];
        
        return $properties;
    }
    
    /**
     * Track event using PostHog PHP SDK
     */
    public static function track_event($event_name, $properties = []) {
        self::log("track_event called: event='{$event_name}', properties=" . json_encode($properties));
        
        if (!self::is_enabled()) {
            self::log("Analytics disabled, not tracking event: {$event_name}");
            return false;
        }
        
        try {
            // Initialize PostHog if not already done
            self::init_posthog();
            
            // Check if PostHog client is properly initialized
            if (!class_exists('PostHog\\PostHog')) {
                self::log("PostHog class not available", 'ERROR');
                return false;
            }
            
            // Final safety check before calling PostHog::capture
            if (!self::$posthog_initialized) {
                self::log("PostHog not marked as initialized, aborting capture", 'ERROR');
                return false;
            }
            
            $final_properties = array_merge(self::get_user_properties(), $properties);
            
            $event_data = [
                'distinctId' => self::get_site_hash(),
                'event' => $event_name,
                'properties' => $final_properties,
                '$process_person_profile' => false // Disable person profile processing for privacy
            ];
            
            self::log("Sending event to PostHog: " . json_encode($event_data));
            
            // Use site hash as distinct ID for privacy
            $result = PostHog::capture($event_data);
            
            self::log("PostHog::capture result: " . (is_bool($result) ? ($result ? 'true' : 'false') : json_encode($result)));
            
            return true;
            
        } catch (Exception $e) {
            self::log("track_event error: " . $e->getMessage(), 'ERROR');
            self::log("track_event stack trace: " . $e->getTraceAsString(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Track page view on admin pages
     */
    public static function track_page_view() {
        if (!self::is_enabled()) {
            return;
        }
        
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'shopmetrics') === false) {
            self::log("Skipping page view tracking - not a ShopMetrics page. Screen: " . ($screen ? $screen->id : 'null'));
            return;
        }
        
        self::log("Tracking page view for: " . $screen->id);
        
        self::track_event('admin_page_viewed', [
            'page' => $screen->id,
            'page_type' => 'php_admin',
            'source' => 'php'
        ]);
    }
    
    /**
     * Track subscription events
     */
    public static function track_subscription_event($action, $plan = null) {
        if (!self::is_enabled()) {
            return;
        }
        
        $properties = [
            'action' => $action
        ];
        
        if ($plan) {
            $properties['subscription_plan'] = $plan;
        }
        
        self::track_event('subscription_event', $properties);
    }
    
    /**
     * Track onboarding events
     */
    public static function track_onboarding_event($step, $action, $data = []) {
        if (!self::is_enabled()) {
            return;
        }
        
        $properties = array_merge([
            'onboarding_step' => $step,
            'onboarding_action' => $action
        ], $data);
        
        self::track_event('onboarding_event', $properties);
    }
    
    /**
     * Track button clicks
     */
    public static function track_button_click($button_type, $location, $additional_data = []) {
        if (!self::is_enabled()) {
            return;
        }
        
        $properties = array_merge([
            'button_type' => $button_type,
            'location' => $location
        ], $additional_data);
        
        self::track_event('button_clicked', $properties);
    }
    
    /**
     * Track feature usage
     */
    public static function track_feature_usage($feature, $action, $data = []) {
        if (!self::is_enabled()) {
            return;
        }
        
        $properties = array_merge([
            'feature' => $feature,
            'feature_action' => $action
        ], $data);
        
        self::track_event('feature_used', $properties);
    }
    
    /**
     * Get debug log content
     */
    public static function get_debug_log() {
        $log_file = self::get_log_file_path();
        if (file_exists($log_file)) {
            return file_get_contents($log_file);
        }
        return '';
    }
    
    /**
     * Get log file path
     */
    public static function get_log_file_path() {
        return self::get_log_file();
    }
    
    /**
     * Clear debug log
     */
    public static function clear_debug_log() {
        $log_file = self::get_log_file_path();
        if (file_exists($log_file)) {
            return file_put_contents($log_file, '');
        }
        return false;
    }
    
    /**
     * Render analytics test page for debugging
     */
    public static function render_test_page() {
        ?>
        <div class="wrap">
            <h1>ShopMetrics Analytics Test</h1>
            
            <div class="card">
                <h2>📊 Analytics Status</h2>
                <p><strong>Analytics Enabled:</strong> <?php echo self::is_enabled() ? '✅ Yes' : '❌ No'; ?></p>
                <p><strong>Analytics Initialized:</strong> <?php echo self::$initialized ? '✅ Yes' : '❌ No'; ?></p>
                <p><strong>PostHog Initialized:</strong> <?php echo self::$posthog_initialized ? '✅ Yes' : '❌ No'; ?></p>
                <p><strong>PostHog API Key:</strong> <?php echo self::POSTHOG_API_KEY ? 'Set (' . esc_html(substr(self::POSTHOG_API_KEY, 0, 20)) . '...)' : 'NOT SET'; ?></p>
                <p><strong>PostHog Host:</strong> <?php echo esc_html(self::POSTHOG_HOST); ?></p>
                <p><strong>User Consent:</strong> <?php echo self::is_enabled() ? '✅ Given' : '❌ Not Given'; ?></p>
            </div>
            
            <?php if (self::is_enabled()): ?>
                <div class="card">
                    <h2>Test Events</h2>
                    <p>Click the buttons below to send test events:</p>
                    
                    <button type="button" class="button button-primary" onclick="sendTestEvent('page_test', {page: 'test_page'})">
                        Test Page View
                    </button>
                    
                    <button type="button" class="button" onclick="sendTestEvent('button_test', {button: 'test_button'})">
                        Test Button Click
                    </button>
                    
                    <button type="button" class="button" onclick="sendTestEvent('feature_test', {feature: 'test_feature'})">
                        Test Feature Usage
                    </button>
                    
                    <div id="test-results" style="margin-top: 20px;"></div>
                </div>
                
                <div class="card">
                    <h2>📋 Debug Log</h2>
                    
                    <?php
                    $log_content = \ShopMetrics\Analytics\ShopMetrics_Analytics::get_debug_log();
                    if (!empty($log_content)) {
                        echo '<div style="background: #000; color: #0f0; padding: 15px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto; white-space: pre-wrap;">';
                        echo esc_html($log_content);
                        echo '</div>';
                        
                        echo '<div class="clearfix">';
                        echo '<button type="button" class="button button-small button-secondary" id="shopmetrics-clear-log">Clear Log</button>';
                        echo '</div>';
                        
                        echo '<script>
                        jQuery(document).ready(function($) {
                            $("#shopmetrics-clear-log").on("click", function() {
                                var data = {
                                    action: "shopmetrics_clear_debug_log",
                                    nonce: "' . esc_js(wp_create_nonce('shopmetrics_analytics_nonce')) . '"
                                };
                                
                                $.post(ajaxurl, data, function(response) {
                                    $("#shopmetrics-debug-log").html("<p>Log cleared.</p>");
                                });
                            });
                        });
                        </script>';
                        
                    } else {
                        echo '<p>No debug information available.</p>';
                    }
                    ?>
                </div>
                
                <script>
                function sendTestEvent(event, properties) {
                    const results = document.getElementById('test-results');
                    results.innerHTML += '<p>Sending ' + event + '...</p>';
                    
                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'shopmetrics_track_event',
                            nonce: '<?php echo esc_js(wp_create_nonce('shopmetrics_analytics_nonce')); ?>',
                            event: event,
                            properties: JSON.stringify(properties)
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            results.innerHTML += '<p style="color: #46b450;">✓ ' + event + ' sent successfully!</p>';
                        } else {
                            results.innerHTML += '<p style="color: #dc3232;">✗ ' + event + ' failed: ' + data.data + '</p>';
                        }
                    })
                    .catch(error => {
                        results.innerHTML += '<p style="color: #dc3232;">✗ ' + event + ' error: ' + error.message + '</p>';
                    });
                }
                </script>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Send test event manually (not during initialization)
     */
    public static function send_test_event() {
        if (!self::is_enabled()) {
            self::log("Analytics disabled, not sending test event");
            return false;
        }
        
        self::log("Sending test analytics event");
        
        return self::track_event('manual_test_event', [
            'test_timestamp' => current_time('Y-m-d H:i:s'),
            'manual_trigger' => true,
            'test_data' => 'PostHog integration working'
        ]);
    }
    
    /**
     * Send test error for debugging
     */
    public static function send_test_error() {
        if (!self::is_enabled()) {
            return [
                'success' => false,
                'message' => 'Analytics is disabled. Please enable analytics consent in settings.'
            ];
        }
        
        self::log("Sending test error event");
        
        // Clear PostHog transient for test to ensure fresh initialization
        $posthog_key = 'shopmetrics_posthog_init_' . get_current_blog_id();
        delete_transient($posthog_key);
        self::$posthog_initialized = false;
        self::log("Cleared transient for test: {$posthog_key}");
        
        // Send test error
        $result = self::track_error(
            'Test Error',
            'This is a test error to verify error tracking functionality',
            __FILE__,
            __LINE__,
            'Test stack trace',
            [
                'test_error' => true,
                'severity' => 'test'
            ]
        );
        
        return [
            'success' => $result,
            'message' => $result ? 'Test error tracked successfully' : 'Failed to track test error'
        ];
    }
    
    /**
     * Test different types of errors for comprehensive testing
     */
    public static function test_error_scenarios() {
        if (!self::is_enabled()) {
            self::log("Analytics disabled, not testing errors");
            return ['success' => false, 'message' => 'Analytics disabled'];
        }
        
        $results = [];
        
        try {
            // Test 1: Manual error tracking
            $trace = (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG && function_exists('debug_backtrace')) 
                ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) 
                : 'debug_backtrace_not_available';
                
            $results['manual_error'] = self::track_error(
                'Manual Test Error',
                'Testing manual error tracking',
                __FILE__,
                __LINE__,
                $trace,
                ['test_scenario' => 'manual', 'timestamp' => time()]
            );
            
            // Test 2: Exception tracking
            try {
                throw new Exception('Test exception for PostHog error tracking');
            } catch (Exception $e) {
                $results['exception_error'] = self::track_exception($e, ['test_scenario' => 'exception']);
            }
            
            // Test 3: WordPress-style error
            $results['wp_error'] = self::track_error(
                'WordPress Error',
                'Simulated WordPress database error',
                ABSPATH . 'wp-includes/wp-db.php',
                1234,
                'WordPress database connection failed',
                ['test_scenario' => 'wordpress', 'error_code' => 'db_connect_fail']
            );
            
            // Test 4: Plugin-specific error
            $results['plugin_error'] = self::track_error(
                'Plugin Error',
                'ShopMetrics plugin configuration error',
                __FILE__,
                __LINE__,
                'Plugin initialization failed',
                ['test_scenario' => 'plugin', 'component' => 'analytics']
            );
            
            self::log("All error scenarios tested successfully");
            
            return [
                'success' => true, 
                'message' => 'All error scenarios tested',
                'results' => $results
            ];
            
        } catch (Exception $e) {
            self::log("Error during testing: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false, 
                'message' => 'Error during testing: ' . $e->getMessage(),
                'results' => $results
            ];
        }
    }
    
    /**
     * Simulate a real fatal error (DO NOT USE IN PRODUCTION!)
     */
    public static function simulate_fatal_error() {
        if (!self::is_enabled()) {
            return ['success' => false, 'message' => 'Analytics disabled'];
        }
        
        self::log("WARNING: Simulating fatal error for testing purposes");
        
        // This will cause a fatal error but track it first
        self::track_error(
            'Simulated Fatal Error',
            'This is a simulated fatal error for testing',
            __FILE__,
            __LINE__,
            'Intentional fatal error simulation',
            ['test_scenario' => 'fatal', 'warning' => 'simulated']
        );
        
        // Uncomment the line below to actually cause a fatal error (BE CAREFUL!)
        // call_to_undefined_function_that_will_cause_fatal_error();
        
        return ['success' => true, 'message' => 'Fatal error simulated (without actually crashing)'];
    }
    
    /**
     * Reset initialization state (for testing or troubleshooting)
     */
    public static function reset_initialization() {
        $init_key = 'shopmetrics_analytics_init_' . get_current_blog_id();
        $posthog_key = 'shopmetrics_posthog_init_' . get_current_blog_id();
        
        delete_transient($init_key);
        delete_transient($posthog_key);
        
        self::$initialized = false;
        self::$posthog_initialized = false;
        
        self::log("Analytics initialization state reset");
        
        return true;
    }
    
    /**
     * Track error/exception event
     */
    public static function track_error($error_type, $message, $file = null, $line = null, $trace = null, $context = []) {
        if (!self::is_enabled()) {
            return false;
        }
        
        $error_properties = array_merge([
            'error_type' => $error_type,
            'error_message' => $message,
            'error_file' => $file,
            'error_line' => $line,
            'stack_trace' => $trace,
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'unknown',
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : 'unknown',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'unknown'
        ], $context);
        
        self::log("Tracking error: {$error_type} - {$message}", 'ERROR');
        
        return self::track_event('error_occurred', $error_properties);
    }
    
    /**
     * Track exception
     */
    public static function track_exception($exception, $context = []) {
        if (!($exception instanceof Exception) && !($exception instanceof Throwable)) {
            return false;
        }
        
        return self::track_error(
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString(),
            $context
        );
    }
    
    /**
     * Register global error handlers (only in debug mode)
     */
    public static function register_error_handlers() {
        if (!self::is_enabled() || !(defined('WP_DEBUG') && WP_DEBUG)) {
            return;
        }
        
        // Register PHP error handler (only in debug mode)
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG && function_exists('set_error_handler')) {
            set_error_handler([__CLASS__, 'handle_php_error']);
        }
        
        // Register exception handler
        set_exception_handler([__CLASS__, 'handle_uncaught_exception']);
        
        // Register fatal error handler
        register_shutdown_function([__CLASS__, 'handle_fatal_error']);
        
        self::log("Error handlers registered");
    }
    
    /**
     * Handle PHP errors
     */
    public static function handle_php_error($severity, $message, $file, $line) {
        // Only track errors, not warnings/notices by default
        if ($severity & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) {
            $trace = (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG && function_exists('debug_backtrace')) 
                ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) 
                : 'debug_backtrace_not_available';
                
            self::track_error(
                'PHP Error',
                $message,
                $file,
                $line,
                $trace,
                ['severity' => $severity]
            );
        }
        
        // Don't interfere with WordPress error handling
        return false;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public static function handle_uncaught_exception($exception) {
        self::track_exception($exception, ['type' => 'uncaught']);
        
        // Don't interfere with WordPress exception handling
        if (class_exists('WP_Error')) {
            // Let WordPress handle it
        }
    }
    
    /**
     * Handle fatal errors
     */
    public static function handle_fatal_error() {
        $error = error_get_last();
        if ($error && $error['type'] & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) {
            self::track_error(
                'Fatal Error',
                $error['message'],
                $error['file'],
                $error['line'],
                null,
                ['type' => 'fatal']
            );
        }
    }

    /**
     * AJAX callback to save analytics consent (enable/disable PostHog tracking)
     */
    public static function ajax_save_analytics_consent() {
        // Verify nonce (nonce field name is _ajax_nonce by default when using jQuery.ajax)
        if (!check_ajax_referer('shopmetrics_save_analytics_consent', '_ajax_nonce', false)) {
            wp_send_json_error('Security check failed', 403);
        }

        // Sanitize & cast consent value: 1 => enabled, 0/anything else => disabled
        $consent_raw = isset($_POST['consent']) ? sanitize_text_field( wp_unslash( $_POST['consent'] ) ) : '0';
        $consent = $consent_raw === '1' || $consent_raw === 'true' ? 1 : 0;

        // Persist option
        $settings = get_option('shopmetrics_settings', []);
        $settings['analytics_consent'] = $consent;
        update_option('shopmetrics_settings', $settings);

        // Clear initialization transient so that analytics init re-evaluates
        $init_key = 'shopmetrics_analytics_init_' . get_current_blog_id();
        delete_transient($init_key);

        // Log and respond
        self::log("Analytics consent set to " . ($consent ? 'enabled' : 'disabled'));
        wp_send_json_success(['consent' => $consent]);
    }
}

// Initialize analytics
add_action('plugins_loaded', ['\\ShopMetrics\\Analytics\\ShopMetrics_Analytics', 'init']);

// Register AJAX handler for saving analytics consent
add_action('wp_ajax_shopmetrics_save_analytics_consent', ['\\ShopMetrics\\Analytics\\ShopMetrics_Analytics', 'ajax_save_analytics_consent']);
add_action('wp_ajax_nopriv_shopmetrics_save_analytics_consent', ['\\ShopMetrics\\Analytics\\ShopMetrics_Analytics', 'ajax_save_analytics_consent']);

// Add AJAX handlers for debug log
add_action('wp_ajax_shopmetrics_get_debug_log', function() {
    if (!check_ajax_referer('shopmetrics_analytics_nonce', 'nonce', false)) {
        wp_die('Security check failed');
    }
    
    $log_content = \ShopMetrics\Analytics\ShopMetrics_Analytics::get_debug_log();
    wp_send_json_success($log_content);
});

add_action('wp_ajax_shopmetrics_clear_debug_log', function() {
    if (!check_ajax_referer('shopmetrics_analytics_nonce', 'nonce', false)) {
        wp_die('Security check failed');
    }
    
    \ShopMetrics\Analytics\ShopMetrics_Analytics::clear_debug_log();
    wp_send_json_success('Debug log cleared');
}); 