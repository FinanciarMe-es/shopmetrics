<?php

namespace ShopMetrics\Analytics;

// Import the Analytics class from the same namespace
use ShopMetrics\Analytics\ShopMetrics_Analytics;

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Financiarme_Analytics
 * @subpackage Financiarme_Analytics/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for enqueuing
 * the admin-specific stylesheet and JavaScript.
 *
 * @package    Financiarme_Analytics
 * @subpackage Financiarme_Analytics/admin
 * @author     FinanciarMe <info@financiarme.es>
 */
class ShopMetrics_Admin {

	/**
	 * A reference to the main plugin instance.
	 *
	 * @since    1.2.0
	 * @var      ShopMetrics    $plugin    The main plugin instance.
	 */
	private $plugin;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	private $plugin_name;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    ShopMetrics    $plugin    The main plugin instance.
	 */
	public function __construct( \ShopMetrics\Analytics\ShopMetrics $plugin ) {
		$this->plugin      = $plugin;
		$this->version     = $plugin->get_version();
		$this->plugin_name = $plugin->get_plugin_name();
	}

    /**
     * Initialize hooks
	 *
	 * @since 1.2.0
     */
    public function init_hooks() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_plugin_settings' ) );
		
		// AJAX handlers
		add_action( 'wp_ajax_shopmetrics_save_token', array( $this, 'ajax_save_token' ) );
		add_action( 'wp_ajax_shopmetrics_save_settings', array( $this, 'handle_ajax_save_settings' ) );
		add_action( 'wp_ajax_shopmetrics_test_api_connection', array( $this, 'handle_ajax_test_connection' ) );
		add_action( 'wp_ajax_shopmetrics_disconnect_site', array( $this, 'ajax_disconnect_site' ) );
		add_action( 'wp_ajax_shopmetrics_sync_data', array( $this, 'ajax_start_sync' ) );
		add_action( 'wp_ajax_shopmetrics_clear_cache', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_shopmetrics_test_recovery_email', array( $this, 'ajax_test_recovery_email' ) );
        add_action( 'wp_ajax_shopmetrics_manual_snapshot', array( $this, 'ajax_manual_snapshot' ) );
        add_action( 'wp_ajax_shopmetrics_fix_snapshot_schedule', array( $this, 'ajax_fix_snapshot_schedule' ) );
        add_action( 'wp_ajax_shopmetrics_clear_logs', array( $this, 'ajax_clear_logs' ) );
        add_action( 'wp_ajax_shopmetrics_download_logs', array( $this, 'ajax_download_logs' ) );
        add_action( 'wp_ajax_shopmetrics_test_order_sync', array( $this, 'ajax_test_order_sync' ) );
        add_action( 'wp_ajax_shopmetrics_check_order_sync', array( $this, 'ajax_check_order_sync' ) );
		add_action( 'wp_ajax_sm_get_all_meta_keys', array( $this, 'ajax_sm_get_all_meta_keys' ) );
		add_action( 'wp_ajax_sm_auto_detect_cogs_key', array( $this, 'ajax_sm_auto_detect_cogs_key' ) );
		add_action( 'wp_ajax_shopmetrics_save_setting', array( $this, 'ajax_save_setting' ) );
		add_action( 'wp_ajax_shopmetrics_start_sync', array( $this, 'ajax_start_sync' ) );
		add_action( 'wp_ajax_shopmetrics_get_billing_history', array( $this, 'ajax_get_billing_history' ) );
		add_action( 'wp_ajax_shopmetrics_create_checkout', array( $this, 'ajax_create_checkout' ) );
		add_action( 'wp_ajax_shopmetrics_get_sync_progress', array( $this, 'ajax_get_sync_progress' ) );
		add_action( 'wp_ajax_shopmetrics_reset_sync_progress', array( $this, 'ajax_reset_sync_progress' ) );
		add_action( 'wp_ajax_shopmetrics_cancel_subscription', array( $this, 'ajax_cancel_subscription' ) );
		add_action( 'wp_ajax_shopmetrics_reactivate_subscription', array( $this, 'ajax_reactivate_subscription' ) );
		add_action( 'wp_ajax_shopmetrics_auto_detect_order_blocks', array( $this, 'ajax_auto_detect_order_blocks' ) );
		add_action( 'wp_ajax_shopmetrics_rotate_logs', array( $this, 'ajax_rotate_logs' ) );
		add_action( 'wp_ajax_shopmetrics_save_analytics_consent', array( $this, 'ajax_save_analytics_consent' ) );
		add_action( 'wp_ajax_shopmetrics_reset_onboarding', array( $this, 'ajax_reset_onboarding' ) );
		add_action( 'wp_ajax_sm_track_visit', array( $this, 'ajax_sm_track_visit' ) );
		add_action( 'wp_ajax_nopriv_sm_track_visit', array( $this, 'ajax_sm_track_visit' ) );
    }

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles($hook_suffix) {
        // Define the dashboard page hook
        $dashboard_page_hook = 'toplevel_page_' . $this->plugin_name;
        $settings_page_hook = 'shopmetrics_page_' . $this->plugin_name . '-settings'; // Hook for submenu page
        $cart_recovery_page_hook = 'shopmetrics_page_' . $this->plugin_name . '-cart-recovery'; // Hook for cart recovery page
        $data_sources_page_hook = 'shopmetrics_page_' . $this->plugin_name . '-data-sources'; // Hook for data sources page
        $subscription_page_hook = 'shopmetrics_page_' . $this->plugin_name . '-subscription'; // Hook for subscription page

        // Debug hook_suffix to console
        if (defined('WP_DEBUG') && WP_DEBUG) {
			\ShopMetrics_Logger::get_instance()->debug("ShopMetrics CSS Hook: " . $hook_suffix . " | Expected: " . $dashboard_page_hook . ", " . $settings_page_hook);
        }

        // Load CSS on all ShopMetrics pages (simplified logic)
		if (empty($this->plugin_name) || strpos($hook_suffix, $this->plugin_name) === false) {
            return;
        }
        
        // Enqueue the admin-specific CSS
        wp_enqueue_style( 
            $this->plugin_name . '-admin',
            plugin_dir_url( dirname( __FILE__ ) ) . 'admin/css/shopmetrics-admin.css', // Correct path
            array(), 
            $this->version . '2.2', // Force cache refresh after fixing green border
            'all' 
        );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts($hook_suffix) {
		// Only enqueue on our plugin's pages
		if ( empty($this->plugin_name) || strpos( $hook_suffix, $this->plugin_name ) === false ) {
			return;
		}

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( dirname( __FILE__ ) ) . 'admin/js/shopmetrics-admin.js', array( 'jquery', 'jquery-ui-slider' ), $this->version . '.2', false );

		// Localize the script with parameters
		wp_localize_script( $this->plugin_name, 'shopmetricsanalytics_admin_params', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'settings_nonce' => wp_create_nonce('sm_settings_ajax_nonce'),
			'server_base_url' => defined('SM_SERVER_BASE_URL') ? SM_SERVER_BASE_URL : '',
			'is_connected' => (get_option('shopmetrics_analytics_api_token') ? 'yes' : 'no'),
			'i18n' => array(
				'confirmation' => __('Are you sure you want to take this action?', 'shopmetrics'),
				'sending' => __('Sending...', 'shopmetrics'),
				'email_sent' => __('Email sent successfully!', 'shopmetrics'),
				'email_error' => __('Error sending email. Please check server logs.', 'shopmetrics'),
				'schedule_fixed' => __('Snapshot schedule fixed!', 'shopmetrics'),
				'schedule_error' => __('Error fixing schedule.', 'shopmetrics'),
				'snapshot_initiated' => __('Snapshot initiated!', 'shopmetrics'),
				'snapshot_error' => __('Error taking snapshot.', 'shopmetrics'),
				// COGS detection translations
				'detecting' => __('Detecting COGS meta key...', 'shopmetrics'),
				'use_this_key' => __('Use This Key', 'shopmetrics'),
				// translators: %s is the selected meta key name
				'key_selected' => __('Selected key: %s', 'shopmetrics'),
				'not_set' => __('Not set', 'shopmetrics'),
				'loading' => __('Loading meta keys...', 'shopmetrics'),
				'error_loading' => __('Error loading meta keys', 'shopmetrics'),
				'error' => __('An error occurred', 'shopmetrics'),
				'ajax_error' => __('Server communication error', 'shopmetrics'),
				'last_synchronization' => __('Last synchronization', 'shopmetrics')
			),
			// Добавлено: debugLogging
			'debugLogging' => (bool) get_option('shopmetrics_enable_debug_logging', false),
		));

		// Additional localization for general UI strings
		wp_localize_script( $this->plugin_name, 'shopmetricsL10n', array(
			'lastSynchronization' => __('Last synchronization', 'shopmetrics')
		));

		// Register additional style & scripts as needed
		wp_enqueue_style('wp-color-picker');
		wp_enqueue_script('wp-color-picker');
		
		// Load specific settings.js file for the settings, data-sources, cart-recovery and subscription pages
		if (strpos($hook_suffix, '-settings') !== false || 
		    strpos($hook_suffix, '-data-sources') !== false ||
		    strpos($hook_suffix, '-cart-recovery') !== false ||
		    strpos($hook_suffix, '-subscription') !== false) {
		    
             wp_enqueue_script(
		        $this->plugin_name . '-settings',
		        plugin_dir_url(dirname(__FILE__)) . 'admin/js/shopmetrics-settings.js',
		        array('jquery'),
				$this->version . '.2', // Force cache refresh
                true // Load in footer
             );
             
		    // Localize with settings-specific data
		    $settings = get_option('shopmetrics_settings', []);
		    wp_localize_script($this->plugin_name . '-settings', 'fmSettings', array(
		        'ajax_url' => admin_url('admin-ajax.php'),
		        'nonce' => wp_create_nonce('sm_settings_ajax_nonce'),
				'cart_recovery_nonce' => wp_create_nonce('sm_cart_recovery_ajax_nonce'),
		        'disconnect_nonce' => wp_create_nonce('shopmetrics_disconnect_action'),
		        'plugin_page' => $hook_suffix,
		        'debug_timestamp' => time(),
		        'text_not_set' => __('Not set', 'shopmetrics'),
		        'text_loading' => __('Loading...', 'shopmetrics'),
		        'text_yes' => __('Yes', 'shopmetrics'),
		        'text_no' => __('No', 'shopmetrics'),
		        'text_ok' => __('OK', 'shopmetrics'),
		        'error_generic' => __('An error occurred', 'shopmetrics'),
		        'error_loading_keys' => __('Error loading meta keys', 'shopmetrics'),
		        'error_ajax' => __('AJAX Error', 'shopmetrics'),
		        // debugLogging теперь из массива
		        'debugLogging' => (bool)($settings['enable_debug_logging'] ?? false),
		        'enableDebugLogs' => (bool)($settings['enable_debug_logging'] ?? false),
		        'analyticsConsentNonce' => wp_create_nonce('shopmetrics_save_analytics_consent'), // Nonce for analytics consent
		    ));
		}

		// For the dashboard page, also load the React app scripts 
		if (strpos($hook_suffix, '-dashboard') !== false || $hook_suffix === 'shopmetrics_page_shopmetrics' || $hook_suffix === 'toplevel_page_shopmetrics') {
			$this->enqueue_react_app_scripts();
		}

		// Analytics now handled entirely in React frontend
	}

    /**
	 * Analytics now handled entirely in React frontend
	 * This method kept for compatibility but does nothing
     *
     * @since 1.0.0
     */
    private function enqueue_analytics_scripts() {
		// Analytics moved to React frontend - no PHP integration needed
            return;
        }



    /**
     * Setup React translations with our new JSON file naming convention
     */
    private function setup_react_translations( $handle, $js_file_url ) {
        $languages_dir = plugin_dir_path( dirname( __FILE__ ) ) . 'languages';
        
        // Extract main filename from full URL and remove .js extension
        $filename = basename( $js_file_url );
        $filename = preg_replace('/\.js$/', '', $filename); // Remove .js extension
        
        // Use only our custom translation loading (WordPress default conflicts with our naming)
        
        // Add our custom JSON file loader as backup
        add_action('admin_footer', function() use ($handle, $filename, $languages_dir) {
            $locale = get_user_locale();
            $json_file = $languages_dir . "/shopmetrics-{$locale}-{$filename}.json";
            $json_url = plugin_dir_url( dirname( __FILE__ ) ) . "languages/shopmetrics-{$locale}-{$filename}.json";
            
            ?>
            <script type="text/javascript">
            // Load our specific JSON translation file
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof wp !== 'undefined' && wp.i18n) {

                    // Check if our specific JSON file exists
                    fetch('<?php echo esc_js($json_url); ?>')
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('JSON file not found: ' + response.status);
                            }
                            return response.json();
                        })
                        .then(data => {                            
                            // Apply translations to wp.i18n
                            if (data.locale_data && data.locale_data['shopmetrics']) {
                                wp.i18n.setLocaleData(data.locale_data['shopmetrics'], 'shopmetrics');                                
                            }
                        })
                        .catch(error => {
                            // console.log('[ShopMetrics] Custom translation file not found, using WordPress defaults:', error.message);
                            // console.log('[ShopMetrics] Attempted URL:', '<?php echo esc_js($json_url); ?>');
                        });
                }
            });
            </script>
            <?php
        }, 1); // High priority to load early
    }
    
    /**
     * Registers the options related to API connection.
     *
     * @since 1.2.1 // Or your current plugin version
     * @access private
     */
    private function _register_connection_options() {
        register_setting(
            'shopmetrics_settings_group', 
            'shopmetrics_analytics_api_token',      
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        register_setting(
            'shopmetrics_settings_group', 
            'shopmetrics_analytics_site_identifier', 
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field', // Or 'esc_url_raw' if it's strictly a URL
                'default' => ''
            )
        );
    }

    /**
     * Renders hidden input fields for connection options.
     *
     * Ensures that API token and site identifier are included in the settings form
     * submission to prevent them from being cleared.
     *
     * @since 1.2.1 // Or your current plugin version
     * @access private
     */
    private function _render_hidden_connection_fields() {
        $api_token_value = get_option( 'shopmetrics_analytics_api_token', '' );
        $site_identifier_value = get_option( 'shopmetrics_analytics_site_identifier', '' );
        
        echo '<input type="hidden" name="shopmetrics_analytics_api_token" value="' . esc_attr( $api_token_value ) . '" />';
        echo '<input type="hidden" name="shopmetrics_analytics_site_identifier" value="' . esc_attr( $site_identifier_value ) . '" />';
    }

    /**
     * Helper function to enqueue React app scripts.
     *
     * @since 1.0.0
     */
    private function enqueue_react_app_scripts() {
        // Define path to the NEW React build output directory
        $react_app_dist_path = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/js/dist/';
        $react_app_dist_url = plugin_dir_url( dirname( __FILE__ ) ) . 'admin/js/dist/';
        
        // Check if the build files exist by looking for *.js files
        $js_files = glob($react_app_dist_path . '*.js');
        if (empty($js_files)) {
             \ShopMetrics_Logger::get_instance()->error('Compiled admin script not found in ' . $react_app_dist_path);
            add_action( 'admin_notices', function() use ($react_app_dist_path) {
                echo '<div class="notice notice-error"><p>' . esc_html__('ShopMetrics Error: Required JavaScript file not found in', 'shopmetrics') . ' ' . esc_html($react_app_dist_path) . '. ' . esc_html__('Please rebuild the React application', 'shopmetrics') . ' (<code>cd react-app && npm run build</code>).</p></div>';
            });
            return;
        }

        $main_js_file_url = '';
        $main_css_file_url = '';
        $script_version = '1.0.0';
        $style_version = '1.0.0';
        $main_js_handle = $this->plugin_name . '-admin-script'; // Simplified handle

        // Scan the dist directory to find the hashed files
        $files = scandir( $react_app_dist_path );
        if ( $files ) {
            foreach ( $files as $file ) {
                // Find the main JS file (e.g., main.hash.js or similar)
                if ( preg_match( '/(main|bundle|index)\..*\.js$/', $file ) ) { // More flexible regex
                    $main_js_file_url = $react_app_dist_url . $file;
                    $script_version = filemtime( $react_app_dist_path . $file );
                }
                // Find the main CSS file (e.g., main.hash.css or similar)
                 if ( preg_match( '/(main|bundle|index)\..*\.css$/', $file ) ) {
                    $main_css_file_url = $react_app_dist_url . $file;
                    $style_version = filemtime( $react_app_dist_path . $file );
                 }
            }
        }
        
        // Enqueue CSS if found
         if( ! empty( $main_css_file_url ) ) {
             wp_enqueue_style(
                 $this->plugin_name . '-admin-style',
                 $main_css_file_url,
                 array(),
                 $style_version
             );
         }
        
        // Only proceed if we found the main JS file
        if ( ! empty( $main_js_file_url ) ) {
             wp_register_script(
                $main_js_handle,
                 $main_js_file_url,
                 array( 'wp-element', 'wp-i18n' ), // wp-element includes react & react-dom, wp-i18n for translations
                 $script_version,
                 true // Load in footer
             );
             
             // Force enqueue wp-i18n specifically to ensure it loads
             wp_enqueue_script('wp-i18n');

            // Prepare data to pass
            $api_token = get_option( 'shopmetrics_analytics_api_token', '' );
            $site_identifier_option = get_option( 'shopmetrics_analytics_site_identifier', '' );

            \ShopMetrics_Logger::get_instance()->debug('[Dashboard Load] - site_identifier_option: ' . $site_identifier_option);

            // Default site identifier to site_url if not set by registration
            $site_identifier = !empty($site_identifier_option) ? $site_identifier_option : esc_url_raw(site_url());
            $is_connected = !empty($api_token) && !empty($site_identifier_option); // Connected only if token AND specific ID exist
            
            // Load translations directly for React - determine filename dynamically
            $locale = get_user_locale();
            $translations = array();
            
            // Find the actual React JS file to match translation filename
            $react_js_files = glob(plugin_dir_path( dirname( __FILE__ ) ) . 'admin/js/dist/main.*.js');
            if (!empty($react_js_files)) {
                $main_js_file = basename($react_js_files[0]);
                $filename = preg_replace('/\.js$/', '', $main_js_file); // Remove .js extension
                
                $json_file = plugin_dir_path( dirname( __FILE__ ) ) . "languages/shopmetrics-{$locale}-{$filename}.json";
                \ShopMetrics_Logger::get_instance()->debug("[Translations] Looking for file: {$json_file}");
                if (file_exists($json_file)) {
                    $json_content = file_get_contents($json_file);
                    $translation_data = json_decode($json_content, true);
                    if ($translation_data && isset($translation_data['locale_data']['shopmetrics'])) {
                        $translations = $translation_data['locale_data']['shopmetrics'];
                        \ShopMetrics_Logger::get_instance()->debug("[Translations] Loaded " . count($translations) . " translation entries");
                    } else {
                        \ShopMetrics_Logger::get_instance()->debug("[Translations] No shopmetrics locale data found in JSON");
                    }
                } else {
                    \ShopMetrics_Logger::get_instance()->debug("[Translations] JSON file not found");
                }
            }
            
            // Determine current page type (basic example)
            $page_type = 'unknown';
            if (is_front_page() || is_home()) {
                $page_type = 'homepage';
            } elseif (is_shop()) {
                $page_type = 'shop_archive';
            } elseif (is_product()) {
                $page_type = 'product';
            } elseif (is_cart()) {
                $page_type = 'cart';
            } elseif (is_checkout()) {
                $page_type = 'checkout';
            } elseif (is_order_received_page()) {
                $page_type = 'purchase_complete';
            }

            // Get subscription status details (example - requires implementation)
            // $subscription_status = $this->get_site_subscription_status($site_identifier); 
            // $cancel_at_timestamp = $this->get_site_cancel_timestamp($site_identifier);
            
            // --- Auto-sync subscription data if stale ---
            if ($is_connected) {
                $this->maybe_sync_subscription_data($site_identifier, $api_token);
            }
            
            $subscription_status = get_option('shopmetrics_subscription_status', 'unknown'); // Placeholder
            $cancel_at_timestamp = get_option('shopmetrics_cancel_at', null); // Placeholder
            $trial_ends_at = get_option('shopmetrics_trial_ends_at', null); // Added trial_ends_at from option
			\ShopMetrics_Logger::get_instance()->info("ShopMetrics PHP [Dashboard Load] - trial_ends_at: " . ($trial_ends_at ? $trial_ends_at . " (" . gmdate("Y-m-d H:i:s", $trial_ends_at) . ")" : "null") . ", subscription_status: " . $subscription_status . ", current_time: " . time());

            // --- Get COGS Meta Key setting ---
			// Get COGS settings from the unified settings array
			$settings = get_option('shopmetrics_settings', []);
			$cogs_meta_key = $settings['cogs_meta_key'] ?? '';
			$cogsDefaultPercentage = $settings['cogs_default_percentage'] ?? null;
			
			// Ensure proper types for React - empty string should be null for percentage
			if ($cogsDefaultPercentage === '' || $cogsDefaultPercentage === false) {
				$cogsDefaultPercentage = null;
			} else if ($cogsDefaultPercentage !== null) {
				$cogsDefaultPercentage = intval($cogsDefaultPercentage);
			}		
            
            // Get onboarding flag
			$needs_onboarding = get_option('shopmetrics_needs_onboarding', 'true') === 'true';
			\ShopMetrics_Logger::get_instance()->info("ShopMetrics [enqueue_react_app_scripts]: needsOnboarding value from DB: " . ($needs_onboarding ? 'true' : 'false'));
            
            // Get logger instance for admin data
            $logger = \ShopMetrics_Logger::get_instance();
            
            // Get analytics configuration for React
            $analytics_config = \ShopMetrics\Analytics\ShopMetrics_Analytics::get_config();
            
            // Prepare data to pass to the React app
            $settings = get_option('shopmetrics_settings', []);
            $data_to_pass = array(
                'apiUrl' => SHOPMETRICS_API_URL, // Use constant defined in main plugin file
                'nonce' => wp_create_nonce( 'shopmetrics_api_actions' ), // Or a specific nonce if needed
                'settingsNonce' => wp_create_nonce( 'sm_settings_ajax_nonce' ), // Add settings nonce
                'errorTestNonce' => wp_create_nonce( 'shopmetrics_nonce' ), // Add error testing nonce
                'ajaxUrl' => admin_url( 'admin-ajax.php' ), // Pass ajaxurl
                'apiToken' => $api_token,
                'siteIdentifier' => $site_identifier,
                'isConnected' => $is_connected,
                'subscriptionStatus' => $subscription_status, // Pass status
                'cancelAt' => $cancel_at_timestamp,     // Pass cancellation time
                'trialEndsAt' => $trial_ends_at,     // Pass trial end time
				'cogsMetaKey' => $cogs_meta_key,
				'cogsDefaultPercentage' => $cogsDefaultPercentage,
                'pageType' => $page_type, // Pass page type
                'currency' => get_woocommerce_currency(),
				'needsOnboarding' => $needs_onboarding ? 'true' : 'false', // Add onboarding flag
                'pluginUrl' => plugin_dir_url( dirname( __FILE__ ) ), // Add plugin URL for assets
				'siteDomain' => wp_parse_url( home_url(), PHP_URL_HOST ), // Add site domain for auto-detection
                'logging' => array(
                    'enabled' => $logger->is_enabled(),
                    'level' => $logger->get_current_log_level(),
                    'fileSize' => $logger->get_log_file_size(),
                    'filePath' => $logger->get_log_file()
                ),
                'translations' => $translations, // Add translations for React
                'locale' => $locale, // Add locale info
                'shopmetricsAnalytics' => $analytics_config, // Add analytics configuration
                // 'orderId' => null // Pass order ID IF on order received page - see visit tracker enqueue
                // Добавлено: debugLogging
                'enableDebugLogs' => !empty($settings['enable_debug_logging']),
                'analyticsConsentNonce' => wp_create_nonce('shopmetrics_save_analytics_consent'), // Nonce for analytics consent
                'settings' => $settings, // Add settings array for PostHog analytics
                'debug' => !empty($settings['enable_debug_logging']), // Add debug flag for PostHog logging
                'siteInfo' => array( // Add site info for PostHog analytics
                    'site_url' => home_url(),
                    'wp_version' => get_bloginfo('version'),
                    'wc_version' => defined('WC_VERSION') ? WC_VERSION : 'unknown',
                    'plugin_version' => SHOPMETRICS_VERSION,
                ),
            );

            \ShopMetrics_Logger::get_instance()->debug('[Dashboard Load] - API Token: ' . ($api_token ? 'Present (length: ' . strlen($api_token) . ')' : 'Not set'));
            \ShopMetrics_Logger::get_instance()->debug('[Dashboard Load] - Site Identifier Option: ' . $site_identifier_option);
            \ShopMetrics_Logger::get_instance()->debug('[Dashboard Load] - Calculated isConnected: ' . ($is_connected ? 'true' : 'false'));
            \ShopMetrics_Logger::get_instance()->debug('[Dashboard Load] - Translations count: ' . count($translations) . ', Locale: ' . $locale);
            \ShopMetrics_Logger::get_instance()->debug('[Dashboard Load] - Translations count: ' . count($translations) . ', Locale: ' . $locale);
        
            // Debug translations before passing to React
            \ShopMetrics_Logger::get_instance()->debug('[Translations] Count: ' . count($translations) . ', Locale: ' . $locale);
            if (!empty($translations)) {
                \ShopMetrics_Logger::get_instance()->debug('[Translations] First few keys: ' . implode(', ', array_slice(array_keys($translations), 0, 5)));
            }
            
			$localize_result = wp_localize_script( $main_js_handle, 'fmData', $data_to_pass );
			
			// Log if localization failed
			if (!$localize_result) {
				\ShopMetrics_Logger::get_instance()->error('wp_localize_script failed for handle: ' . $main_js_handle);
			}

            wp_enqueue_script( $main_js_handle );
            
            // Set up JavaScript translations for the React app with our new naming convention
            $this->setup_react_translations( $main_js_handle, $main_js_file_url );
            
            // Translations are handled by setup_react_translations() above
            
            // Add debugging for translations - with proper wp.i18n waiting
            add_action('admin_footer', function() use ($main_js_file_url, $main_js_handle) {
                ?>
                <script type="text/javascript">
                // Wait for wp.i18n to be available and test translations
                function testTranslations() {
                    if (typeof wp !== 'undefined' && wp.i18n && wp.i18n.__) {                        
                        // Check locale data
                        if (window.wp.i18n.getLocaleData) {
                            const localeData = window.wp.i18n.getLocaleData('shopmetrics');
                        }
                        return true;
                    } else {
                        return false;
                    }
                }
                
                // Try immediately
                if (!testTranslations()) {
                    // If not available, try after 1 second
                    setTimeout(function() {
                        if (!testTranslations()) {
                            // Try after 3 seconds
                            setTimeout(function() {
                                testTranslations();
                            }, 2000);
                        }
                    }, 1000);
                }
                </script>
                <?php
            }, 100);
            
            // For extra safety, explicitly add an inline script to verify window.fmData exists
            add_action('admin_footer', function() use ($data_to_pass) {
                ?>
                <script type="text/javascript">
                // Backup in case wp_localize_script fails
                if (typeof window.fmData === 'undefined') {
					console.warn('[ShopMetrics] fmData is undefined - wp_localize_script may have failed. Creating backup data object.');
                    window.fmData = <?php echo wp_json_encode($data_to_pass); ?>;
					console.log('[ShopMetrics] Backup fmData created:', window.fmData);
				} else {
					console.log('[ShopMetrics] fmData is properly loaded:', window.fmData);
                }
                
                // Create analytics configuration for React hook
                if (window.fmData && window.fmData.shopmetricsAnalytics) {
                    // Merge with WordPress localized script data if available
                    if (typeof shopmetricsAnalytics !== "undefined") {
                        window.shopmetricsAnalytics = {
                            ...window.fmData.shopmetricsAnalytics,
                            ...shopmetricsAnalytics // WordPress localized data has nonce and ajaxUrl
                        };
                    } else {
                        window.shopmetricsAnalytics = window.fmData.shopmetricsAnalytics;
                    }
                }
                
                // Create global translation function for React
                window.__ = function(text, domain) {
                    if (typeof wp !== 'undefined' && wp.i18n && wp.i18n.__) {
                        return wp.i18n.__(text, domain || 'shopmetrics');
                    }
                    return text; // Fallback to original text
                };
                
                </script>
                <?php
            }, 99); // High priority to run late
        } else {
            \ShopMetrics_Logger::get_instance()->error('Main JS file for React app not found after scanning.');
             add_action( 'admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . esc_html__('ShopMetrics Error: Could not find main JavaScript file for the dashboard. Please try rebuilding the application.', 'shopmetrics') . '</p></div>';
            });
        }
	}
    
    /**
	 * Add the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {

        // Add Top-Level Menu Page (will likely show the first submenu by default)
        add_menu_page(
            __( 'ShopMetrics for WooCommerce', 'shopmetrics' ), // Page title
            __( 'ShopMetrics', 'shopmetrics' ),           // Menu title
            'manage_options',                                      // Capability
            $this->plugin_name,                                    // Menu slug
            array( $this, 'display_dashboard_page' ),             // Point default to dashboard
            'dashicons-chart-area',                                // Icon URL
            6                                                      // Position
        );
        
        // Add Dashboard Submenu Page
        $dashboard_hook = add_submenu_page(
            $this->plugin_name,                                    // Parent slug
            __( 'Dashboard', 'shopmetrics' ),           // Page title
            __( 'Dashboard', 'shopmetrics' ),           // Menu title
            'manage_options',                                      // Capability
            $this->plugin_name,                                    // Menu slug (same as parent for default)
            array( $this, 'display_dashboard_page' )              // Callback function
        );

        // Add Settings Submenu Page
        $settings_hook = add_submenu_page(
            $this->plugin_name,                                    // Parent slug
            __( 'Settings', 'shopmetrics' ),            // Page title
            __( 'Settings', 'shopmetrics' ),            // Menu title
            'manage_options',                                      // Capability
            $this->plugin_name . '-settings',                     // Menu slug (unique)
            array( $this, 'display_settings_page' )               // Callback function
        );
        
        // Add Data Sources Submenu Page
        $data_sources_hook = add_submenu_page(
            $this->plugin_name,                                    // Parent slug
            __( 'Data Sources', 'shopmetrics' ),        // Page title
            __( 'Data Sources', 'shopmetrics' ),        // Menu title
            'manage_options',                                      // Capability
            $this->plugin_name . '-data-sources',                 // Menu slug (unique)
            array( $this, 'display_data_sources_page' )           // Callback function
        );
        
        // Add Cart Recovery Submenu Page
        $cart_recovery_hook = add_submenu_page(
            $this->plugin_name,                                    // Parent slug
            __( 'Cart Recovery', 'shopmetrics' ),       // Page title
            __( 'Cart Recovery', 'shopmetrics' ),       // Menu title
            'manage_options',                                      // Capability
            $this->plugin_name . '-cart-recovery',                // Menu slug (unique)
            array( $this, 'display_cart_recovery_page' )          // Callback function
        );
        
        // Add Subscription Submenu Page
        $subscription_hook = add_submenu_page(
            $this->plugin_name,                                    // Parent slug
            __( 'Subscription', 'shopmetrics' ),        // Page title
            __( 'Subscription', 'shopmetrics' ),        // Menu title
            'manage_options',                                      // Capability
            $this->plugin_name . '-subscription',                 // Menu slug (unique)
            array( $this, 'display_subscription_page' )           // Callback function
        );
        
        // Add Test Analytics Submenu Page (for debugging)
        // if (defined('WP_DEBUG') && WP_DEBUG) {
        //     $test_analytics_hook = add_submenu_page(
        //         $this->plugin_name,                                    // Parent slug
        //         __( 'Test Analytics', 'shopmetrics' ),      // Page title
        //         __( 'Test Analytics', 'shopmetrics' ),      // Menu title
        //         'manage_options',                                      // Capability
        //         $this->plugin_name . '-test-analytics',               // Menu slug (unique)
        //         array( $this, 'display_test_analytics_page' )         // Callback function
        //     );
        // }
        
        // Store hooks if needed for enqueue logic
        // add_action( "load-{$dashboard_hook}", array( $this, 'load_dashboard_assets' ) );
        // add_action( "load-{$settings_hook}", array( $this, 'load_settings_assets' ) );
	}

	/**
	 * Render the Dashboard page for this plugin.
	 *
	 * @since    1.0.0
	 */
    public function display_dashboard_page() {
		// Handle reset onboarding URL parameter
		if (isset($_GET['reset_onboarding']) && sanitize_text_field(wp_unslash($_GET['reset_onboarding'])) === '1') {
			if (current_user_can('manage_options')) {
				        update_option('shopmetrics_needs_onboarding', 'true');
				delete_transient('shopmetrics_onboarding_progress');
				\ShopMetrics_Logger::get_instance()->info("Onboarding reset via URL parameter");
				
				// Redirect to clean URL to avoid repeated resets
				wp_redirect(admin_url('admin.php?page=shopmetrics'));
				exit;
			}
		}
		
        // Check if the site is connected
        $api_token = get_option('shopmetrics_analytics_api_token', '');
        $site_identifier = get_option('shopmetrics_analytics_site_identifier', '');
        $is_connected = !empty($api_token) && !empty($site_identifier);
        
		// Check if onboarding is needed - check if onboarding was completed
		$needs_onboarding = get_option('shopmetrics_needs_onboarding', 'true') === 'true';
        
        echo '<div class="wrap">';

        // Always create the root mounting div for React
        echo '<div id="shopmetrics-dashboard-root">';
        
        // If not connected, show a notice instead of loading the dashboard
        if (!$is_connected && !$needs_onboarding) {
            // Single styled panel with all information
            echo '<div style="background-color: #fde9ef; padding: 20px; margin-top: 20px; border: 1px solid #d63638; border-radius: 8px;">';
            // Logo and heading inside the panel
            echo '<div style="margin-bottom: 15px; display: flex; align-items: center;">';
            echo '<img src="' . esc_url(plugin_dir_url(dirname(__FILE__)) . 'admin/images/financiarme-logo.svg') . '" alt="ShopMetrics" style="height: 35px; margin-right: 10px;">';
            echo '<h3 style="margin: 0; color: #d63638; font-size: 24px;">' . esc_html__('ShopMetrics for WooCommerce - Setup required', 'shopmetrics') . '</h3>';
            echo '</div>';
            echo '<p style="font-size: 15px;">' . esc_html__('Your site is not connected to ShopMetrics for WooCommerce. The dashboard requires a connection to display your data.', 'shopmetrics') . '</p>';
            echo '<ol style="font-size: 15px; line-height: 1.5;">';
            echo '<li>' . esc_html__('Go to the Settings page by clicking the button below', 'shopmetrics') . '</li>';
            echo '<li>' . esc_html__('Click on the "Connect & Synchronize" button', 'shopmetrics') . '</li>';
            echo '<li>' . esc_html__('Wait for the connection process to complete', 'shopmetrics') . '</li>';
            echo '<li>' . esc_html__('Once connected, return to this page to view your dashboard', 'shopmetrics') . '</li>';
            echo '</ol>';
            echo '<p style="margin-top: 15px; text-align: center;">';
            echo '<a href="' . esc_url(admin_url('admin.php?page=' . $this->plugin_name . '-settings')) . '" class="button button-primary button-large">' . 
                esc_html__('Go to Settings Page', 'shopmetrics') . '</a>';
            echo '</p>';
            echo '</div>';
        } else {
            // Site is connected, show loading spinner inside the root element
            echo '<div style="display: flex; justify-content: center; align-items: center; height: 150px;">
                <svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="20" cy="20" r="18" fill="none" stroke="#dddddd" stroke-width="4"></circle>
                    <circle cx="20" cy="20" r="18" fill="none" stroke="#2271b1" stroke-width="4" stroke-dasharray="113" stroke-dashoffset="0" style="animation: sm-analytics-dash 1.5s ease-in-out infinite">
                        <animate attributeName="stroke-dashoffset" values="113;0" dur="1.5s" repeatCount="indefinite" />
                    </circle>
                </svg>
            </div>
            <style>
                @keyframes sm-analytics-dash {
                    0% { stroke-dashoffset: 113; }
                    50% { stroke-dashoffset: 30; }
                    100% { stroke-dashoffset: 113; }
                }
            </style>';
            
            // Enqueue analytics script for React pages
            \ShopMetrics\Analytics\ShopMetrics_Analytics::enqueue_analytics_script('shopmetrics_dashboard');
            
            // React app scripts and fmData
            $react_app_dist_url = plugin_dir_url( dirname( __FILE__ ) ) . 'admin/js/dist/';
            $react_app_dist_path = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/js/dist/';
            
            // Get analytics configuration
            $analytics_config = \ShopMetrics\Analytics\ShopMetrics_Analytics::get_config();
            
            // Prepare data to be passed to the app
            $settings = get_option('shopmetrics_settings', []);
            $data_to_pass = array(
                'apiUrl' => SHOPMETRICS_API_URL,
                'nonce' => wp_create_nonce('shopmetrics_api_actions'),
                'settingsNonce' => wp_create_nonce('sm_settings_ajax_nonce'),
                'errorTestNonce' => wp_create_nonce('shopmetrics_nonce'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'apiToken' => $api_token,
                'siteIdentifier' => $site_identifier,
                'isConnected' => $is_connected,
                'subscriptionStatus' => 'active', // Placeholder
                'cancelAt' => null,
                'cogsMetaKey' => $settings['cogs_meta_key'] ?? '',
                'cogsDefaultPercentage' => $settings['cogs_default_percentage'] ?? null,
                'pageType' => 'dashboard',
                'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
                'needsOnboarding' => $needs_onboarding ? 'true' : 'false', // Make sure this is a string 'true'/'false'
                'pluginUrl' => plugin_dir_url( dirname( __FILE__ ) ), // Add plugin URL for assets
                'siteDomain' => wp_parse_url( home_url(), PHP_URL_HOST ), // Add site domain for auto-detection
                'shopmetricsAnalytics' => $analytics_config, // Add analytics configuration
                // Добавлено: debugLogging
                'enableDebugLogs' => !empty($settings['enable_debug_logging']),
                'analyticsConsentNonce' => wp_create_nonce('shopmetrics_save_analytics_consent'), // Nonce for analytics consent
                'settings' => $settings, // Add settings array for PostHog analytics
                'debug' => !empty($settings['enable_debug_logging']), // Add debug flag for PostHog logging
                'siteInfo' => array( // Add site info for PostHog analytics
                    'site_url' => home_url(),
                    'wp_version' => get_bloginfo('version'),
                    'wc_version' => defined('WC_VERSION') ? WC_VERSION : 'unknown',
                    'plugin_version' => SHOPMETRICS_VERSION,
                ),
            );
            
            // Check if we have built React files
            $files = scandir($react_app_dist_path);
            $has_main_js = false;
            $main_js_file = '';
            $main_css_file = '';
            
            if ($files) {
                foreach ($files as $file) {
                    // Find main JS file
                    if (preg_match('/(main|bundle|index)\..*\.js$/', $file)) {
                        $has_main_js = true;
                        $main_js_file = $react_app_dist_url . $file;
                    }
                    // Find main CSS file
                    if (preg_match('/(main|bundle|index)\..*\.css$/', $file)) {
                        $main_css_file = $react_app_dist_url . $file;
                    }
                }
            }
            
            // Now add the needed scripts and styles using WordPress enqueue functions
            if ($has_main_js) {
                // Enqueue CSS if found
                if (!empty($main_css_file)) {
                    wp_enqueue_style('shopmetrics-dashboard-css', $main_css_file, array(), SHOPMETRICS_VERSION);
                }
                
                // Create consistent handle name
                $main_js_handle = 'shopmetrics-dashboard-js';
                
                // Enqueue main app script
                wp_enqueue_script($main_js_handle, $main_js_file, array(), SHOPMETRICS_VERSION, true);
                
                // Localize script data
                $localize_result = wp_localize_script($main_js_handle, 'fmData', $data_to_pass);
                
                // Log if localization failed
                if (!$localize_result) {
                    \ShopMetrics_Logger::get_instance()->error('wp_localize_script failed for handle: ' . $main_js_handle);
                }
                
                // Add inline script for initialization
                $inline_script = '
                    // Create analytics configuration for React hook
                    if (window.fmData && window.fmData.shopmetricsAnalytics) {
                        // Merge with WordPress localized script data if available
                        if (typeof shopmetricsAnalytics !== "undefined") {
                            window.shopmetricsAnalytics = {
                                ...window.fmData.shopmetricsAnalytics,
                                ...shopmetricsAnalytics // WordPress localized data has nonce and ajaxUrl
                            };
                        } else {
                            window.shopmetricsAnalytics = window.fmData.shopmetricsAnalytics;
                        }
                    }
                    
                    // Create global translation function for React BEFORE loading React
                    window.__ = function(text, domain) {
                        if (typeof wp !== "undefined" && wp.i18n && wp.i18n.__) {
                            return wp.i18n.__(text, domain || "shopmetrics");
                        }
                        return text; // Fallback to original text
                    };
                    
                    // Create backup function to ensure onboarding shows if fmData is missing
                    window.checkAndFixFmData = function() {
                        if (typeof window.fmData === "undefined") {
                            console.warn("[ShopMetrics] fmData is undefined - wp_localize_script may have failed. Creating backup data object.");
                            window.fmData = ' . wp_json_encode($data_to_pass) . ';
                            window.fmData.needsOnboarding = "true"; // Force onboarding when data is missing
                            console.log("[ShopMetrics] Backup fmData created:", window.fmData);
                        } else {
                            console.log("[ShopMetrics] fmData is properly loaded:", window.fmData);
                        }
                    };
                    
                    // Check multiple times to ensure fmData is available
                    window.checkAndFixFmData();
                    setTimeout(window.checkAndFixFmData, 100);
                    setTimeout(window.checkAndFixFmData, 500);
                
                    // Check if React mounted correctly, if not, ensure mounting point exists
                    setTimeout(function() {
                        var mountPoint = document.getElementById("shopmetrics-dashboard-root");
                        if (!mountPoint || mountPoint.childElementCount === 0) {
                            console.error("React failed to mount - ensuring root element exists");
                            var wrap = document.querySelector(".wrap");
                            if (wrap && !mountPoint) {
                                mountPoint = document.createElement("div");
                                mountPoint.id = "shopmetrics-dashboard-root";
                                wrap.appendChild(mountPoint);
                            }
                        }
                    }, 1000);
                ';
                wp_add_inline_script($main_js_handle, $inline_script, 'before');
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('ShopMetrics Error: Could not find main JavaScript file for the dashboard. Please rebuild the application.', 'shopmetrics') . '</p></div>';
            }
        }
        
        // Close the React root element
        echo '</div>';
        
        // Close the wrap div
        echo '</div>';
    }

	/**
	 * Render the Settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_settings_page() {
        echo '<div class="wrap">';
        
        // Explicit global declaration to ensure it's accessible to the template
        $GLOBALS['shopmetricsanalytics_admin'] = $this;
        
        // Backup in case $GLOBALS doesn't work properly
        global $shopmetricsanalytics_admin;
        $shopmetricsanalytics_admin = $this;
        
        // Include the settings template file
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/settings-settings.php';
        
        echo '</div>'; // Close .wrap
	}

    /**
     * Register plugin settings using the Settings API.
     *
     * @since 1.0.0
     */
    public function register_plugin_settings() {
        // Call the new method to register connection options
        $this->_register_connection_options();

        // Register the unified settings array
        register_setting(
            'shopmetrics_settings_group',
            'shopmetrics_settings',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings_array'),
                'default' => array(
                    'enable_visit_tracking' => true,
                    'enable_low_stock_notifications' => false,
                    'cogs_meta_key' => '',
                    'cogs_default_percentage' => null,
                    // Cart recovery defaults
                    'enable_cart_recovery_emails' => false,
                    'cart_abandonment_threshold' => 4,
                    'cart_recovery_email_delay' => 1,
                    'cart_recovery_include_coupon' => false,
                    'cart_recovery_email_sender_name' => '',
                    'cart_recovery_email_sender_email' => '',
                    'cart_recovery_email_subject' => '',
                    'cart_recovery_button_text' => '',
                    'cart_recovery_link_expiry' => 7,
                    'cart_recovery_email_content' => '',
                    'cart_recovery_coupon_code' => ''
                )
            )
        );

        // Add settings section
        add_settings_section(
            'shopmetrics_analytics_general_section', // Section ID
            __( 'General Settings', 'shopmetrics' ), // Title
            null, // No specific callback needed for section description here
            'shopmetrics-settings' // Page slug where this section appears
        );

        // Add field for enabling/disabling visit tracking
        add_settings_field(
            'shopmetrics_settings_enable_visit_tracking', // Field ID
            __( 'Enable Visit Tracking', 'shopmetrics' ), // Title
            array( $this, 'render_enable_visit_tracking_field' ), // Callback to render the field
            'shopmetrics-settings', // Page
            'shopmetrics_analytics_general_section' // Section
        );

        // COGS settings are now part of the unified shopmetrics_settings array
        // No separate registration needed - they're handled in the sanitize_settings_array method
        
        // --- Register Enable Low Stock Email Notifications Setting ---
        register_setting(
            'shopmetrics_settings_group',
            'shopmetrics_analytics_enable_low_stock_notifications',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false
            )
        );

        // --- Add Enable Low Stock Email Notifications Field ---
        add_settings_field(
            'shopmetrics_analytics_enable_low_stock_notifications_id',
            __( 'Enable Low Stock Email Notifications', 'shopmetrics' ),
            array( $this, 'render_enable_low_stock_notifications_field' ), // Callback to be defined next
            'shopmetrics-settings',
            'shopmetrics_analytics_general_section'
        );

        // --- Register Low Stock Notification Recipients Setting ---
        register_setting(
            'shopmetrics_analytics_settings_group',
            'shopmetrics_analytics_low_stock_notification_recipients',
            array(
                'type' => 'string',
                'sanitize_callback' => array( $this, 'sanitize_low_stock_recipients' ), // Callback to be defined next
                'default' => ''
            )
        );

        // --- Add Low Stock Notification Recipients Field ---
        add_settings_field(
            'shopmetrics_analytics_low_stock_notification_recipients_id',
            __( 'Low Stock Notification Recipient(s)', 'shopmetrics' ),
            array( $this, 'render_low_stock_notification_recipients_field' ), // Callback to be defined next
            'shopmetrics-settings',
            'shopmetrics_analytics_general_section'
        );
        
        // Register Cart Recovery Settings
        // (Cart Recovery settings are registered in the Cart_Recovery class)

        // --- Debug Logging Setting ---
        register_setting(
            'shopmetrics_analytics_settings_group',
            'shopmetrics_enable_debug_logging',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false
            )
        );
        add_settings_field(
            'shopmetrics_enable_debug_logging_id',
            __( 'Enable Debug Logging', 'shopmetrics' ),
            array( $this, 'render_enable_debug_logging_field' ),
            'shopmetrics-settings',
            'shopmetrics_analytics_general_section'
        );
    }
    
    /**
     * Sanitizes the order blocks value.
     *
     * @since 1.3.0
     * @param mixed $input The input value to sanitize.
     * @return int The sanitized order blocks value (1-10).
     */
    public function sanitize_order_blocks($input) {
        $value = intval($input);
        
        // Make sure value is between 1 and 10
        if ($value < 1 || $value > 10) {
            add_settings_error(
                'shopmetrics_selected_order_blocks',
                'invalid_order_blocks',
                __('Order blocks must be between 1 and 10.', 'shopmetrics'),
                'error'
            );
            return get_option('shopmetrics_selected_order_blocks', 1);
        }
        
        return $value;
    }

    /**
     * Renders the input field for the COGS Meta Key setting.
     *
     * @since 1.1.0
     */
    public function render_cogs_meta_key_field() {
        $settings = get_option('shopmetrics_settings', []);
        $value = $settings['cogs_meta_key'] ?? '';
        ?>
        <div id="sm_cogs_meta_key_setting_area">
            <p>
                <strong><?php esc_html_e( 'Currently Saved Key:', 'shopmetrics' ); ?></strong>
                <code id="sm_current_cogs_key_display"><?php echo esc_html( $value ? $value : __( 'Not set', 'shopmetrics' ) ); ?></code>
            </p>

            <button type="button" id="sm_auto_detect_cogs_key_button" class="button">
                <?php esc_html_e( 'Auto-detect COGS Meta Key', 'shopmetrics' ); ?>
            </button>
            <button type="button" id="sm_manual_select_cogs_key_button" class="button">
                <?php esc_html_e( 'Select Key Manually', 'shopmetrics' ); ?>
            </button>
            
            <div id="sm_cogs_detection_result_area" style="margin-top: 10px; padding: 10px; border: 1px solid #eee; display: none;">
                <!-- JS will populate this -->
            </div>

            <div id="sm_cogs_manual_select_area" style="margin-top: 10px; display: none;">
                <select id="sm_cogs_meta_key_dropdown" name="shopmetrics_settings[cogs_meta_key]_dropdown_select" style="min-width: 200px;">
                    <option value=""><?php esc_html_e( '-- Select a Key --', 'shopmetrics' ); ?></option>
                    <option value=""><?php esc_html_e( '-- Do not use a meta key --', 'shopmetrics' ); ?></option> 
                    <!-- JS will populate this -->
                </select>
                <p class="description"><?php esc_html_e( 'Select the meta key for COGS. If your key is not listed, ensure orders with that meta key exist or check advanced options if available.', 'shopmetrics' ); ?></p>
            </div>

            <!-- Hidden input that actually saves the value -->
            <input 
                type="hidden" 
                id="shopmetrics_settings_cogs_meta_key_hidden_input" 
                name="shopmetrics_settings[cogs_meta_key]" 
                value="<?php echo esc_attr( $value ); ?>" 
            />

            <p class="description" style="margin-top:15px;">
                <?php esc_html_e( 'The plugin will attempt to find the Cost of Goods Sold (COGS) for each order item using this meta key.', 'shopmetrics' ); ?>
                <br>
                <?php printf(
                    /* translators: %s: Example meta key */
                    esc_html__( 'Common example: %s. Leave blank or select "-- Do not use a meta key --" if you only want to use the default percentage or not track COGS via item meta.', 'shopmetrics' ),
                    '<code>_wc_cog_item_cost</code>'
                ); ?>
            </p>
        </div>
        <?php
    }

    /**
    * Renders the input field for the Default COGS Percentage setting.
    */
    public function render_cogs_default_percentage_field() {
        $settings = get_option('shopmetrics_settings', []);
        $value = $settings['cogs_default_percentage'] ?? ''; 
        ?>
        <input 
            type="number" 
            step="1" 
            min="0" 
            max="100" 
            id="shopmetrics_settings_cogs_default_percentage" 
            name="shopmetrics_settings[cogs_default_percentage]" 
            value="<?php echo esc_attr( $value ); ?>" 
            class="regular-text"
            placeholder="<?php esc_attr_e( 'e.g., 60 for 60%', 'shopmetrics' ); ?>"
        /> %
        <p class="description">
            <?php esc_html_e( 'If per-item COGS is not found via the meta key, use this percentage of the item\\\'s pre-discount subtotal to estimate COGS (0-100).', 'shopmetrics' ); ?>
            <br>
            <?php esc_html_e( 'Leave blank if you only use meta key COGS or do not want to estimate.', 'shopmetrics' ); ?>
        </p>
        <?php
    }
 
    /**
     * Sanitizes the COGS percentage value.
     */
    public function sanitize_cogs_percentage( $input ) {
        if ( $input === '' || $input === null ) {
            return null; 
        }
        $value = filter_var( $input, FILTER_VALIDATE_INT );
        if ( $value === false || $value < 0 || $value > 100 ) {
            add_settings_error(
                'shopmetrics_settings',
                'invalid_cogs_percentage',
                __( 'Default COGS Percentage must be an integer between 0 and 100, or blank.', 'shopmetrics' ),
                'error'
            );
            $settings = get_option('shopmetrics_settings', []);
            return $settings['cogs_default_percentage'] ?? null; 
        }
        return $value;
    }    

    /**
     * Renders the checkbox field for enabling low stock email notifications.
     *
     * @since 1.0.0
     */
    public function render_enable_low_stock_notifications_field() {
        $settings = get_option('shopmetrics_settings', []);
        ?>
        <input
            type="hidden"
            name="shopmetrics_settings[enable_low_stock_notifications]"
            value="0"
        />
        <input
            type="checkbox"
            id="shopmetrics_enable_low_stock_notifications"
            name="shopmetrics_settings[enable_low_stock_notifications]"
            value="1"
            <?php checked(!empty($settings['enable_low_stock_notifications'])); ?>
        />
        <p class="description"><?php esc_html_e( 'Check this box to enable daily email notifications for products that have reached their low stock threshold.', 'shopmetrics' ); ?></p>
        <?php
    }

        /**
     * Renders the input field for low stock notification recipients.
     *
     * @since 1.0.0
     */
    public function render_low_stock_notification_recipients_field() {
        $option_name = 'shopmetrics_analytics_low_stock_notification_recipients';
        $value = get_option( $option_name, '' ); // Default to empty string
        ?>
        <input
            type="text"
            id="<?php echo esc_attr( $option_name ); ?>"
            name="<?php echo esc_attr( $option_name ); ?>"
            value="<?php echo esc_attr( $value ); ?>"
            class="regular-text"
            placeholder="<?php esc_attr_e( 'e.g., admin@example.com, manager@example.com', 'shopmetrics' ); ?>"
        />
        <p class="description">
            <?php esc_html_e( 'Enter one or more email addresses, separated by commas. These addresses will receive the low stock notifications if enabled.', 'shopmetrics' ); ?>
            <br>
            <?php esc_html_e( 'If this field is left empty, notifications will be sent to the site administrator\\\'s email by default if notifications are enabled.', 'shopmetrics' ); ?>
        </p>
        <?php
    }

    /**
     * Sanitizes the low stock notification recipients list.
     *
     * @since 1.0.0
     * @param string $input The input string of comma-separated emails.
     * @return string The sanitized string of comma-separated valid emails.
     */
    public function sanitize_low_stock_recipients( $input ) {
        if ( empty( trim( $input ) ) ) {
            return ''; // Return empty if input is blank or only whitespace
        }

        $emails = array_map( 'trim', explode( ',', $input ) );
        $valid_emails = array();

        foreach ( $emails as $email ) {
            if ( is_email( $email ) ) {
                $valid_emails[] = $email;
            } elseif ( ! empty( $email ) ) { // Only add error if it's not an empty string resulting from multiple commas
                add_settings_error(
                    'shopmetrics_analytics_low_stock_notification_recipients', // Setting slug
                    'invalid_email_in_list',                                 // Error code
                    sprintf(                                                  // Message
                        // translators: %s is the invalid email address that was removed
                        __( 'The email address "%s" is not valid and has been removed.', 'shopmetrics' ),
                        esc_html( $email )
                    ),
                    'warning' // Type of message (error, warning, success, info)
                );
            }
        }
        // Return unique, valid emails, joined by a comma and a space for readability and standard storage.
        return implode( ', ', array_unique( $valid_emails ) );
    }

    /**
     * Renders the checkbox field for the enable visit tracking setting.
     *
     * @since 1.0.0
     */
    public function render_enable_visit_tracking_field() {
        $settings = get_option('shopmetrics_settings', []);
        ?>
        <input
            type="hidden"
            name="shopmetrics_settings[enable_visit_tracking]"
            value="0"
        />
        <input
            type="checkbox"
            id="shopmetrics_enable_visit_tracking"
            name="shopmetrics_settings[enable_visit_tracking]"
            value="1"
            <?php checked(!empty($settings['enable_visit_tracking'])); ?>
        />
        <p class="description"><?php esc_html_e( 'Check this box to enable the tracking of website visits.', 'shopmetrics' ); ?></p>
        <?php
    }
    
    /**
     * AJAX handler for disconnecting the site.
     *
     * Deletes the local API token and nonce.
     * TODO: Trigger backend API call to mark site as inactive/delete data.
     *
     * @since 1.0.0
     */
    public function ajax_disconnect_site() {
        // Log request for debugging
        \ShopMetrics_Logger::get_instance()->info("Disconnect site AJAX request received");
        
        // Verify nonce
        if (!isset($_POST['_ajax_nonce'])) {
            \ShopMetrics_Logger::get_instance()->error("Disconnect site failed - nonce not provided");
            wp_send_json_error(['message' => 'Nonce not provided'], 400);
            return;
        }
        
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_ajax_nonce'])), 'shopmetrics_disconnect_action')) {
            \ShopMetrics_Logger::get_instance()->error("Disconnect site failed - nonce verification failed");
            wp_send_json_error(['message' => 'Security check failed'], 403);
            return;
        }

        // Check user capability
        if (!current_user_can('manage_options')) {
            \ShopMetrics_Logger::get_instance()->error("Disconnect site failed - insufficient permissions");
            wp_send_json_error(['message' => 'You do not have permission to perform this action'], 403);
            return;
        }

        // Get credentials before deleting them
        $api_token = get_option('shopmetrics_analytics_api_token', '');
        $site_identifier = get_option('shopmetrics_analytics_site_identifier', '');

        // Notify API about site disconnection before deleting local credentials
        if (!empty($api_token) && !empty($site_identifier)) {
            $disconnect_url = SHOPMETRICS_API_URL . '/v1/disconnect-site';
            $disconnect_args = array(
                'method' => 'POST',
                'timeout' => 10,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-FinanciarMe-Token' => $api_token,
                    'X-FinanciarMe-Site-Identifier' => $site_identifier
                ),
                'body' => json_encode(array(
                    'status' => 'inactive'  // Set site connection status to inactive
                ))
            );

            $disconnect_response = wp_remote_post($disconnect_url, $disconnect_args);
            if (is_wp_error($disconnect_response)) {
                \ShopMetrics_Logger::get_instance()->error("Failed to notify API about site disconnection: " . $disconnect_response->get_error_message());
            } else {
                $response_code = wp_remote_retrieve_response_code($disconnect_response);
                \ShopMetrics_Logger::get_instance()->info("Notified API about site disconnection, response code: " . $response_code);
            }
        }

        // Delete all related options
        $deleted_token = delete_option('shopmetrics_analytics_api_token');
        $deleted_site_id = delete_option('shopmetrics_analytics_site_identifier');
        $all_deleted = $deleted_token && $deleted_site_id;

        \ShopMetrics_Logger::get_instance()->info("Disconnect site - token deleted: " . ($deleted_token ? 'yes' : 'no'));
        \ShopMetrics_Logger::get_instance()->info("Disconnect site - site ID deleted: " . ($deleted_site_id ? 'yes' : 'no'));

        if ($deleted_token || $deleted_site_id) {
            // Trigger site disconnection action for webhook removal
            do_action('shopmetrics_analytics_site_disconnected');
            \ShopMetrics_Logger::get_instance()->info("Triggered site disconnection action for webhook removal");
            
            // At least one option was successfully deleted
            wp_send_json_success(['message' => 'Site disconnected successfully']);
        } else {
            // No options were deleted (possibly they didn't exist)
            wp_send_json_success(['message' => 'Site disconnected (no data needed to be removed)']);
        }
        
        wp_die(); // Required for AJAX handlers
    }

    /**
     * Syncs site subscription information from the backend API to WordPress options.
     * Fetches current subscription status, trial period, and other subscription-related data.
     * 
     * @since    1.3.0
     * @param    string    $site_identifier    The site identifier to fetch information for
     * @param    string    $api_token          The API token for authentication
     * @return   bool                          True on success, false on failure
     */
    private function sync_subscription_info($site_identifier, $api_token) {
        if (empty($site_identifier) || empty($api_token)) {
            \ShopMetrics_Logger::get_instance()->info("Cannot sync subscription info - missing site identifier or API token");
            return false;
        }

        // Get WooCommerce currency if available
        $currency = 'EUR'; // Default fallback
        if (function_exists('get_woocommerce_currency')) {
            $woo_currency = get_woocommerce_currency();
            if (!empty($woo_currency)) {
                $currency = $woo_currency;
            }
        }

        // Construct the API endpoint URL with currency parameter
        $api_url = SHOPMETRICS_API_URL;
        $api_url = rtrim($api_url, '/') . '/site-info?currency=' . urlencode($currency);

        \ShopMetrics_Logger::get_instance()->info("Syncing subscription info from API for site: {$site_identifier}, currency: {$currency}");

        // Make the API request to get site info
        $response = wp_remote_get($api_url, array(
            'timeout' => 15,
            'headers' => array(
                'X-FinanciarMe-Token' => $api_token,
                'X-FinanciarMe-Site-Identifier' => $site_identifier
            )
        ));

        if (is_wp_error($response)) {
            \ShopMetrics_Logger::get_instance()->info("Error fetching subscription info: " . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        
        // Handle specific HTTP error codes
        if ($response_code === 401) {
            \ShopMetrics_Logger::get_instance()->info("401 Unauthorized - Token may be invalid or expired");
            // For 401s, we should still set a default subscription status to avoid showing "No Active Subscription"
            update_option('shopmetrics_subscription_status', 'free');
            \ShopMetrics_Logger::get_instance()->info("Set default 'free' status after 401 response");
            return false;
        }
        
        if ($response_code !== 200) {
            \ShopMetrics_Logger::get_instance()->info("API returned non-200 response when fetching subscription info: {$response_code}");
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data) {
            \ShopMetrics_Logger::get_instance()->info("Failed to parse API response when fetching subscription info");
            return false;
        }

        // Update subscription status option
        if (isset($data['subscription_status'])) {
            update_option('shopmetrics_subscription_status', $data['subscription_status']);
            \ShopMetrics_Logger::get_instance()->info("Updated subscription status to: " . $data['subscription_status']);
        }

        // Update trial end date if present
        if (isset($data['trial_ends_at']) && is_numeric($data['trial_ends_at'])) {
            update_option('shopmetrics_trial_ends_at', $data['trial_ends_at']);
            \ShopMetrics_Logger::get_instance()->info("Updated trial_ends_at to: " . $data['trial_ends_at']);
        }

        // Update next billing date if present
        if (isset($data['next_billing_date'])) {
            update_option('shopmetrics_next_billing_date', $data['next_billing_date']);
            \ShopMetrics_Logger::get_instance()->info("Updated next_billing_date to: " . $data['next_billing_date']);
        }

        // Update customer info if present
        if (isset($data['customer_email'])) {
            update_option('shopmetrics_customer_email', $data['customer_email']);
            \ShopMetrics_Logger::get_instance()->info("Updated customer_email to: " . $data['customer_email']);
        }

        if (isset($data['customer_name'])) {
            update_option('shopmetrics_customer_name', $data['customer_name']);
            \ShopMetrics_Logger::get_instance()->info("Updated customer_name to: " . $data['customer_name']);
        }

        if (isset($data['customer_type'])) {
            update_option('shopmetrics_customer_type', $data['customer_type']);
            \ShopMetrics_Logger::get_instance()->info("Updated customer_type to: " . $data['customer_type']);
        }

        if (isset($data['vat_number'])) {
            update_option('shopmetrics_vat_number', $data['vat_number']);
            \ShopMetrics_Logger::get_instance()->info("Updated vat_number to: " . $data['vat_number']);
        }

        // Update pricing information if present
        if (isset($data['pricing'])) {
            update_option('shopmetrics_pricing_data', json_encode($data['pricing']));
            \ShopMetrics_Logger::get_instance()->info("Updated pricing data from API");
        }

        // Update cancellation date if present
        if (isset($data['cancel_at']) && is_numeric($data['cancel_at'])) {
            update_option('shopmetrics_cancel_at', $data['cancel_at']);
            \ShopMetrics_Logger::get_instance()->info("Updated cancel_at to: " . $data['cancel_at']);
        } else {
            // If no cancel_at in the response, remove any existing value
            delete_option('shopmetrics_cancel_at');
        }

        return true;
    }

    /**
     * Check if subscription data is stale and sync if needed.
     * This ensures dashboard always has fresh subscription data.
     *
     * @since 1.0.0
     * @param string $site_identifier The site identifier
     * @param string $api_token The API token
     */
    private function maybe_sync_subscription_data($site_identifier, $api_token) {
        if (empty($site_identifier) || empty($api_token)) {
            return;
        }

        // Check when subscription data was last synced
        $last_sync = get_option('shopmetrics_subscription_last_sync', 0);
        $current_time = time();
        $sync_interval = 6 * HOUR_IN_SECONDS; // Sync every 6 hours

        // Check if we need to sync (data is older than 6 hours or never synced)
        if (($current_time - $last_sync) > $sync_interval) {
            \ShopMetrics_Logger::get_instance()->info("Auto-syncing subscription data (last sync: " . 
                     ($last_sync ? gmdate('Y-m-d H:i:s', $last_sync) : 'never') . ")");
            
            // Attempt to sync subscription info
            $sync_result = $this->sync_subscription_info($site_identifier, $api_token);
            
            if ($sync_result) {
                // Update last sync timestamp
                update_option('shopmetrics_subscription_last_sync', $current_time);
                \ShopMetrics_Logger::get_instance()->info("Auto-sync completed successfully");
            } else {
                \ShopMetrics_Logger::get_instance()->info("Auto-sync failed, will retry on next dashboard load");
            }
        } else {
            $next_sync = $last_sync + $sync_interval;
            \ShopMetrics_Logger::get_instance()->info("Subscription data is fresh (next sync: " . gmdate('Y-m-d H:i:s', $next_sync) . ")");
        }
    }

    /**
     * AJAX handler for saving the API token.
     *
     * @since 1.0.0
     */
    public function ajax_save_token() {
        \ShopMetrics_Logger::get_instance()->info("ajax_save_token called");
        
        // Verify nonce (use the action name from wp_create_nonce)
        $nonce_received = isset( $_POST['_ajax_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ) ) : '';
        $nonce_valid = wp_verify_nonce( $nonce_received, 'shopmetrics_api_actions' );
        
        \ShopMetrics_Logger::get_instance()->info("ajax_save_token: Nonce received: " . $nonce_received . ", Valid: " . ($nonce_valid ? 'yes' : 'no'));
        
        if ( ! $nonce_received || ! $nonce_valid ) {
            \ShopMetrics_Logger::get_instance()->error("ajax_save_token: Nonce verification failed - received: " . $nonce_received . ", valid: " . ($nonce_valid ? 'yes' : 'no'));
            wp_send_json_error( array( 'message' => __( 'Nonce verification failed!', 'shopmetrics' ) ), 403 );
        }

        // Check user capability
        if ( ! current_user_can( 'manage_options' ) ) {
            \ShopMetrics_Logger::get_instance()->error("ajax_save_token: User lacks manage_options capability");
             wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'shopmetrics' ) ), 403 );
        }
        
        // Get the token from the POST data
        $api_token = isset( $_POST['api_token'] ) ? sanitize_text_field( wp_unslash( $_POST['api_token'] ) ) : '';
        // Get the site identifier from POST data
        $site_identifier_from_ajax = isset( $_POST['site_identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['site_identifier'] ) ) : '';

        \ShopMetrics_Logger::get_instance()->info("ajax_save_token: Processing token: " . substr($api_token, 0, 10) . "... and site_identifier: " . $site_identifier_from_ajax);

        if ( empty( $api_token ) ) {
            \ShopMetrics_Logger::get_instance()->error("ajax_save_token: No API token provided");
             wp_send_json_error( array( 'message' => __( 'No API token provided.', 'shopmetrics' ) ), 400 );
        }

        // Save the API token option
        // update_option returns false if value is unchanged or on failure. True if value changed.
        $token_updated = update_option( 'shopmetrics_analytics_api_token', $api_token );
        $current_token_in_db = get_option('shopmetrics_analytics_api_token');
        $token_save_considered_successful = ($token_updated || ($api_token === $current_token_in_db));


        // Save the site identifier option if provided
        $identifier_updated = false; // Assume not updated initially
        $identifier_save_considered_successful = false;

        if ( ! empty( $site_identifier_from_ajax ) ) {
            $identifier_updated = update_option( 'shopmetrics_analytics_site_identifier', $site_identifier_from_ajax );
            $current_identifier_in_db = get_option('shopmetrics_analytics_site_identifier');
            $identifier_save_considered_successful = ($identifier_updated || ($site_identifier_from_ajax === $current_identifier_in_db));
            
            if ($identifier_save_considered_successful) {
                \ShopMetrics_Logger::get_instance()->info("Site identifier processed successfully: " . $site_identifier_from_ajax . ($identifier_updated ? " (Saved)" : " (Unchanged)"));
            } else {
                \ShopMetrics_Logger::get_instance()->info("Failed to save site identifier: " . $site_identifier_from_ajax);
            }
        } else {
            \ShopMetrics_Logger::get_instance()->info("No site_identifier provided in ajax_save_token call.");
        }

        if ( $token_save_considered_successful ) {
             \ShopMetrics_Logger::get_instance()->info("API token processed successfully." . ($token_updated ? " (Saved)" : " (Unchanged)"));
             
             // Sync subscription info from API after successful token save
             if ($identifier_save_considered_successful) {
                try {
                // Attempt to sync subscription info
                $sync_result = $this->sync_subscription_info($site_identifier_from_ajax, $api_token);
                
                // Update last sync timestamp if successful
                if ($sync_result) {
                    update_option('shopmetrics_subscription_last_sync', time());
                        \ShopMetrics_Logger::get_instance()->info("ajax_save_token: Subscription sync completed successfully");
                } else {
                        \ShopMetrics_Logger::get_instance()->info("ajax_save_token: Failed to sync subscription info from API, but continuing with connection.");
                }
                
                // Trigger site connected action for webhook creation
                do_action('shopmetrics_analytics_site_connected');
                    \ShopMetrics_Logger::get_instance()->info("ajax_save_token: Triggered site connected action for webhook creation");
                } catch (Exception $e) {
                    \ShopMetrics_Logger::get_instance()->error("ajax_save_token: Exception during sync: " . $e->getMessage());
                    // Continue anyway - sync failure shouldn't block connection
                }
             }
             
             $message = __( 'API token processed successfully.', 'shopmetrics' );
             if (!empty($site_identifier_from_ajax)) {
                 if ($identifier_save_considered_successful) {
                     $message = __( 'API token and Site Identifier processed successfully.', 'shopmetrics' );
                 } else {
                     $message = __( 'API token processed. Site Identifier failed to save.', 'shopmetrics' );
                 }
             }
             \ShopMetrics_Logger::get_instance()->info("ajax_save_token: Sending success response: " . $message);
            wp_send_json_success( array( 'message' => $message ) );
        } else {
             \ShopMetrics_Logger::get_instance()->info("Failed to save API token.");
             wp_send_json_error( array( 'message' => __( 'Failed to save API token.', 'shopmetrics' ) ), 500 );
        }

        wp_die(); // Required for AJAX handlers
    }

    /**
     * AJAX handler for triggering the historical data synchronization.
     *
     * @since 1.0.0
     */
    public function ajax_start_sync() {
        // Verify nonce
        if ( ! isset( $_POST['_ajax_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ) ), 'shopmetrics_api_actions' ) ) {
            \ShopMetrics_Logger::get_instance()->debug('Nonce verification failed');
            wp_send_json_error( array( 'message' => __( 'Nonce verification failed!', 'shopmetrics' ) ), 403 );
        }

        // Check user capability
        if ( ! current_user_can( 'manage_options' ) ) {
            \ShopMetrics_Logger::get_instance()->debug('User does not have manage_options capability');
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'shopmetrics' ) ), 403 );
        }
        
        // Check if Action Scheduler is available
        if ( ! class_exists('ActionScheduler_Store') || ! function_exists( 'as_schedule_single_action' ) ) {
            \ShopMetrics_Logger::get_instance()->debug('Action Scheduler not found. Cannot schedule historical sync.');
            wp_send_json_error( array( 'message' => __( 'Action Scheduler is required but not active.', 'shopmetrics' ) ), 500 );
        }

        // Clean up any stuck actions first as a precaution
        if (function_exists('as_unschedule_all_actions')) {
            \ShopMetrics_Logger::get_instance()->debug('Cleaning up any potentially stuck sync actions');
            as_unschedule_all_actions('shopmetrics_analytics_sync_historical_orders');
        }

        // Schedule the sync action (avoid scheduling if already pending/running)
        $hook = 'shopmetrics_analytics_sync_historical_orders';
        $scheduled_actions = as_get_scheduled_actions( array(
            'hook' => $hook,
            'status' => array( \ActionScheduler_Store::STATUS_PENDING, \ActionScheduler_Store::STATUS_RUNNING ),
            'per_page' => 1,
        ), 'ids' );
        
        // Check if historical sync is already in progress via the progress option
        $progress_data = get_option( 'sm_historical_sync_progress', '' );
        $progress = !empty($progress_data) ? (is_string($progress_data) ? json_decode($progress_data, true) : $progress_data) : null;
        \ShopMetrics_Logger::get_instance()->debug('Current progress data: ' . wp_json_encode($progress));
        
        if ( is_array( $progress ) && isset( $progress['status'] ) && 
             $progress['status'] === 'in_progress' && 
             isset( $progress['timestamp'] ) && 
             ( time() - intval( $progress['timestamp'] ) ) < 300 // Less than 5 minutes old
        ) {
            $message = __( 'Historical synchronization is already in progress.', 'shopmetrics' );
            \ShopMetrics_Logger::get_instance()->debug('Historical sync requested but already in progress according to progress data.');
            wp_send_json_error( array( 
                'message' => $message,
                'progress' => $progress 
            ), 409 ); // 409 Conflict
            return;
        }
        
        // Reset the progress record to start fresh
        $initial_progress = [
            'status' => 'starting',
            'progress' => 0,
            'total_orders' => 0,
            'processed_orders' => 0,
            'last_processed_id' => 0,
            'last_synced_date' => null,
            'timestamp' => time()
        ];
        
        $update_result = update_option('sm_historical_sync_progress', json_encode($initial_progress));
        \ShopMetrics_Logger::get_instance()->debug('Reset progress data, update result: ' . ($update_result ? 'true' : 'false'));
        
        if ( ! empty( $scheduled_actions ) ) {
            // Force mark any existing actions as complete
            foreach ($scheduled_actions as $action_id) {
                if (function_exists('as_mark_complete_action')) {
                    as_mark_complete_action($action_id);
                    \ShopMetrics_Logger::get_instance()->info("ShopMetrics Sync AJAX: Forced mark action ID $action_id as complete");
                }
            }
            
            // Try scheduling again after clearing existing actions
            try {
                $action_id = as_schedule_single_action( time() + 1, $hook, array(), 'shopmetrics-sync' );
                if (!$action_id) {
                    $message = __( 'Failed to schedule sync action after clearing existing schedule.', 'shopmetrics' );
                    \ShopMetrics_Logger::get_instance()->debug('' . $message);
                    wp_send_json_error( array( 'message' => $message ), 500 );
                    return;
                }
            } catch (\Exception $e) {
                $message = __( 'Error scheduling synchronization after clearing existing schedule: ', 'shopmetrics' ) . $e->getMessage();
                \ShopMetrics_Logger::get_instance()->debug('' . $message);
                wp_send_json_error( array( 'message' => $message ), 500 );
                return;
            }
            
            $message = __( 'Historical synchronization rescheduled after clearing previous schedule.', 'shopmetrics' );
            \ShopMetrics_Logger::get_instance()->debug('' . $message);
        } else {
            try {
                $action_id = as_schedule_single_action( time() + 1, $hook, array(), 'shopmetrics-sync' );
                if ( !$action_id ) {
                    throw new \Exception(__( 'Failed to schedule sync action.', 'shopmetrics' ));
                }
            } catch ( \Exception $e ) {
                $message = __( 'Error scheduling synchronization: ', 'shopmetrics' ) . $e->getMessage();
                \ShopMetrics_Logger::get_instance()->debug('Error scheduling historical sync: ' . $e->getMessage());
                wp_send_json_error( array( 'message' => $message ), 500 );
                return;
            }
        }
        
        $message = __( 'Historical data synchronization scheduled successfully.', 'shopmetrics' );
        \ShopMetrics_Logger::get_instance()->debug('Historical sync scheduled. Action ID: ' . $action_id);
        
        // Start the first batch immediately for faster response
        if (class_exists('ShopMetrics\\Analytics\\Orders_Tracker')) {
            try {
                // Get Orders_Tracker directly instead of through the plugin instance
                $orders_tracker_class = '\\ShopMetrics\\Analytics\\Orders_Tracker';
                $orders_tracker_file = plugin_dir_path(dirname(__FILE__)) . 'includes/class-shopmetrics-orders-tracker.php';
                
                if (file_exists($orders_tracker_file)) {
                    require_once($orders_tracker_file);
                    
                    // Execute the action directly instead of trying to call the method
                    \ShopMetrics_Logger::get_instance()->debug('Triggering immediate sync through action hook');
                    do_action('shopmetrics_analytics_sync_historical_orders');
                } else {
                    \ShopMetrics_Logger::get_instance()->debug('Orders_Tracker file not found - skipping immediate execution');
                }
            } catch (\Exception $e) {
                \ShopMetrics_Logger::get_instance()->debug('Error during immediate sync execution: ' . $e->getMessage());
            }
        }
        
        wp_send_json_success( array( 
            'message' => $message,
            'progress' => $initial_progress
        ));
    }

    /**
     * AJAX handler to create Stripe Checkout Session via backend.
     *
     * @since 1.0.0
     */
    public function ajax_create_checkout() {
        // Debug logging for nonce verification
        $received_nonce = isset( $_POST['_ajax_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ) ) : 'NOT_SET';
        $expected_nonce = wp_create_nonce('sm_settings_ajax_nonce');
        \ShopMetrics_Logger::get_instance()->debug('AJAX create_checkout - Received nonce: ' . $received_nonce);
        \ShopMetrics_Logger::get_instance()->debug('AJAX create_checkout - Expected nonce: ' . $expected_nonce);
        \ShopMetrics_Logger::get_instance()->debug('AJAX create_checkout - Expected nonce action: sm_settings_ajax_nonce');
        \ShopMetrics_Logger::get_instance()->debug('AJAX create_checkout - Nonce verification result: ' . (wp_verify_nonce($received_nonce, 'sm_settings_ajax_nonce') ? 'VALID' : 'INVALID'));
        
        // Verify nonce 
         if ( ! isset( $_POST['_ajax_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ) ), 'sm_settings_ajax_nonce' ) ) {
            \ShopMetrics_Logger::get_instance()->error('AJAX create_checkout - Nonce verification failed. Received: ' . $received_nonce);
            wp_send_json_error( array( 'message' => __( 'Nonce verification failed!', 'shopmetrics' ) ), 403 );
        }
        \ShopMetrics_Logger::get_instance()->debug('AJAX create_checkout - Nonce verification successful');

        // Check user capability
        if ( ! current_user_can( 'manage_options' ) ) {
             \ShopMetrics_Logger::get_instance()->error('AJAX create_checkout - User capability check failed');
             wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'shopmetrics' ) ), 403 );
        }
        
        \ShopMetrics_Logger::get_instance()->debug('AJAX create_checkout - User capability check successful');
        
        // REMOVED: Stripe library check and Secret Key handling - Not needed here.
        // The backend Lambda handles Stripe initialization and secret key.

        try {
                     // --- Get necessary data ---
         $site_identifier_from_url = esc_url_raw( site_url() );
         $site_identifier_option = get_option( 'shopmetrics_analytics_site_identifier', '' );
         $api_token = get_option( 'shopmetrics_analytics_api_token', '' );

         // Use the site identifier from option if available, otherwise fallback to site_url()
         $site_identifier = !empty($site_identifier_option) ? $site_identifier_option : $site_identifier_from_url;

         \ShopMetrics_Logger::get_instance()->debug("AJAX create_checkout - Site identifier from site_url(): {$site_identifier_from_url}");
         \ShopMetrics_Logger::get_instance()->debug("AJAX create_checkout - Site identifier from option: {$site_identifier_option}");
         \ShopMetrics_Logger::get_instance()->debug("AJAX create_checkout - Using site identifier: {$site_identifier}");
         \ShopMetrics_Logger::get_instance()->debug("AJAX create_checkout - API token (first 10 chars): " . substr($api_token, 0, 10) . "...");

             if ( empty( $api_token ) ) {
                 wp_send_json_error([ 'message' => 'Site is not connected to FinanciarMe.' ], 403); // Forbidden if not connected
             }

            // --- Call the backend to create the session ---
            $backend_url = SHOPMETRICS_API_URL . '/v1/create-checkout-session'; // Adjust endpoint
            
            \ShopMetrics_Logger::get_instance()->debug("AJAX create_checkout - Backend URL: {$backend_url}");
            
            // --- Generate Success/Cancel URLs ---
            $success_url = add_query_arg( 'stripe_checkout', 'success', admin_url( 'admin.php?page=' . $this->plugin_name . '-subscription' ) );
            $cancel_url = add_query_arg( 'stripe_checkout', 'cancel', admin_url( 'admin.php?page=' . $this->plugin_name . '-subscription' ) );

            // --- Get customer type from request ---
            $customer_type = isset($_POST['customer_type']) ? sanitize_text_field( wp_unslash( $_POST['customer_type'] ) ) : 'B2C';
            if (!in_array($customer_type, ['B2B', 'B2C'])) {
                $customer_type = 'B2C'; // Default fallback
            }

            // --- Get VAT number from request ---
            $vat_number = isset($_POST['vat_number']) ? sanitize_text_field( wp_unslash( $_POST['vat_number'] ) ) : '';
            if (!empty($vat_number)) {
                $vat_number = strtoupper(trim($vat_number)); // Normalize VAT number
            }

            // --- Get currency from request ---
            $currency = isset($_POST['currency']) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '';
            if (empty($currency)) {
                // Fallback to WooCommerce currency if available
                $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'EUR';
            }

            $headers = array(
                'Content-Type'                  => 'application/json', // Backend expects JSON
                'X-FinanciarMe-Site-Identifier' => $site_identifier,
                'X-FinanciarMe-Token'           => $api_token,
            );
            
            \ShopMetrics_Logger::get_instance()->debug("AJAX create_checkout - Headers: " . json_encode($headers));
            $body_data = array(
                'success_url' => $success_url,
                'cancel_url'  => $cancel_url,
                'plan_id' => 'SHOPMETRICS_YEARLY', // New yearly plan at 99 EUR/year
                'customer_type' => $customer_type, // B2B or B2C for tax reporting
                'vat_number' => $vat_number, // VAT number for EU B2B customers
                'currency' => $currency, // Preferred currency for pricing
                // Add any other data the backend might need from WP here
            );
            $args = array(
                'method'  => 'POST',
                'headers' => $headers,
                'timeout' => 20,
                'body'    => json_encode($body_data), // Send URLs in the body
            );

            \ShopMetrics_Logger::get_instance()->info("ShopMetrics Admin: Calling backend create-checkout-session for $site_identifier with success_url: $success_url");
            $response = wp_remote_post( $backend_url, $args );

            if ( is_wp_error( $response ) ) {
                \ShopMetrics_Logger::get_instance()->error("ShopMetrics Checkout Error calling backend: " . $response->get_error_message());
                wp_send_json_error([ 'message' => 'Error communicating with backend: ' . $response->get_error_message() ], 500);
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( $response_code === 200 && isset( $data['url'] ) ) {
                 // Send the checkout URL back to the frontend
                wp_send_json_success([ 'url' => $data['url'] ]);
            } else {
                 // Log detailed error from backend if available
                $error_message = isset($data['message']) ? $data['message'] : 'Failed to create checkout session.';
                 \ShopMetrics_Logger::get_instance()->error("ShopMetrics Checkout Error from backend (Code: {$response_code}): {$error_message} - Body: {$body}");
                wp_send_json_error([ 'message' => $error_message ], $response_code >= 400 ? $response_code : 500); // Use backend code if error, else 500
            }

        // REMOVED: Stripe specific exception handling - Keep general exception handling
        } catch ( Exception $e ) {
            \ShopMetrics_Logger::get_instance()->error("ShopMetrics Checkout General Error: " . $e->getMessage() );
            wp_send_json_error( [ 'message' => 'An unexpected error occurred: ' . $e->getMessage() ], 500 );
        }
    }

    /**
     * AJAX handler to request subscription cancellation via backend.
     *
     * @since 1.0.0
     */
    public function ajax_cancel_subscription() {
        // Verify nonce  
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sm_settings_ajax_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce verification failed!', 'shopmetrics' ) ), 403 );
        }

        // Check user capability
        if ( ! current_user_can( 'manage_options' ) ) {
             wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'shopmetrics' ) ), 403 );
        }

        // Get cancellation feedback from form
                    $cancellation_reason = isset($_POST['reason']) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
            $cancellation_feedback = isset($_POST['feedback']) ? sanitize_textarea_field( wp_unslash( $_POST['feedback'] ) ) : '';

        // Get required credentials
        $api_token = get_option( 'shopmetrics_analytics_api_token', '' );
        $site_identifier = esc_url_raw( site_url() );
        $backend_url = SHOPMETRICS_API_URL . '/v1/cancel-subscription'; // Backend cancellation endpoint

        if ( empty( $api_token ) || empty( $site_identifier ) ) {
            wp_send_json_error( array( 'message' => __( 'Site is not connected. Cannot manage subscription.', 'shopmetrics' ) ), 400 );
        }

        // Prepare cancellation data to send to backend
        $cancellation_data = array(
            'cancellation_reason' => $cancellation_reason,
            'cancellation_feedback' => $cancellation_feedback,
            'timestamp' => time(),
            'user_id' => get_current_user_id()
        );

        // Prepare headers for backend request
        $headers = array(
            'Content-Type'                  => 'application/json',
            'X-FinanciarMe-Site-Identifier' => $site_identifier,
            'X-FinanciarMe-Token'           => $api_token,
        );
        $args = array(
            'method'  => 'POST',
            'headers' => $headers,
            'timeout' => 20, 
            'body'    => json_encode($cancellation_data) // Send cancellation data
        );
        
        \ShopMetrics_Logger::get_instance()->info("ShopMetrics Admin: Calling backend /cancel-subscription for " . $site_identifier . " with reason: " . $cancellation_reason);
        $response = wp_remote_post( $backend_url, $args );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            \ShopMetrics_Logger::get_instance()->error("ShopMetrics Admin Error calling backend cancel endpoint: " . $error_message);
            // translators: %s is the error message from the subscription service
            wp_send_json_error( array( 'message' => sprintf(__( 'Error communicating with subscription service: %s', 'shopmetrics' ), $error_message) ), 500 );
        } else {
            $response_code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( $response_code === 200 && isset( $data['success'] ) && $data['success'] === true ) {
                \ShopMetrics_Logger::get_instance()->info("ShopMetrics Admin: Successfully requested subscription cancellation for " . $site_identifier);
                // Pass the success message and cancel_at timestamp back to JS
                wp_send_json_success( array( 
                    'message' => $data['message'] ?? __( 'Cancellation requested.', 'shopmetrics' ),
                    'cancel_at' => $data['cancel_at'] ?? null 
                ) ); 
            } else {
                $error_message = isset($data['message']) ? $data['message'] : __( 'Unknown error from subscription service.', 'shopmetrics' );
                \ShopMetrics_Logger::get_instance()->error("ShopMetrics Admin Error from backend cancel endpoint: Code " . $response_code . ' Body: ' . $body);
                // translators: %1$d is the error code, %2$s is the error message
                wp_send_json_error( array( 'message' => sprintf(__( 'Subscription service error (Code: %1$d): %2$s', 'shopmetrics' ), $response_code, esc_html($error_message)) ), $response_code >= 400 ? $response_code : 500 );
            }
        }
        
        wp_die(); // Required for AJAX handlers
    }

    /**
     * AJAX handler for reactivating a subscription scheduled for cancellation.
     *
     * @since 1.0.0
     */
    public function ajax_reactivate_subscription() {
        // 1. Check Nonce
        check_ajax_referer( 'sm_settings_ajax_nonce', 'nonce' );

        // 2. Check Capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'shopmetrics' ) ), 403 );
            wp_die();
        }

        // 3. Get required data (API token, site identifier)
        $api_token = get_option( 'shopmetrics_analytics_api_token', '' );
        $site_identifier = esc_url_raw( site_url() );

        if ( empty( $api_token ) ) {
             wp_send_json_error( array( 'message' => __( 'API token not found. Cannot reactivate subscription.', 'shopmetrics' ) ), 400 );
             wp_die();
        }

        // 4. Call Backend API Endpoint
        $reactivate_url = SHOPMETRICS_API_URL . '/v1/reactivate-subscription'; // Define the endpoint
        $headers = array(
            'Content-Type'                  => 'application/json',
            'X-FinanciarMe-Site-Identifier' => $site_identifier,
            'X-FinanciarMe-Token'           => $api_token,
        );
        $args = array(
            'method'  => 'POST', // Use POST for reactivation
            'headers' => $headers,
            'timeout' => 20, // Slightly longer timeout for potentially slower backend operations
        );

        \ShopMetrics_Logger::get_instance()->info("ShopMetrics Admin: Attempting to reactivate subscription for " . $site_identifier );
        $response = wp_remote_post( $reactivate_url, $args );

        // 5. Handle Backend Response
        if ( is_wp_error( $response ) ) {
            \ShopMetrics_Logger::get_instance()->error("ShopMetrics Admin Error reactivating subscription: " . $response->get_error_message() );
            wp_send_json_error( array( 'message' => __( 'Error communicating with the backend API.', 'shopmetrics' ) . ' (' . $response->get_error_message() . ')' ), 500 );
            wp_die();
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $response_code === 200 && isset( $data['success'] ) && $data['success'] === true ) {
            // Reactivation successful on backend
             \ShopMetrics_Logger::get_instance()->info("ShopMetrics Admin: Subscription reactivation successful for " . $site_identifier );
             
             // 6. Update local WP options
             update_option( 'shopmetrics_subscription_status', 'active' );
             update_option( 'shopmetrics_cancel_at', null ); // Clear the cancellation timestamp
             
             // 7. Send success response to frontend
             wp_send_json_success( array( 'message' => __( 'Subscription successfully reactivated.', 'shopmetrics' ) ) );
        } else {
            // Reactivation failed on backend or unexpected response
             $error_message = isset($data['message']) ? $data['message'] : __( 'Unknown error from backend.', 'shopmetrics' );
             \ShopMetrics_Logger::get_instance()->error("ShopMetrics Admin Error reactivating subscription: Code " . $response_code . ' Body: ' . $body );
             // translators: %s is the error message from the backend
             wp_send_json_error( array( 'message' => sprintf(__( 'Failed to reactivate subscription. Backend response: %s', 'shopmetrics' ), esc_html($error_message) ) ), $response_code );
        }

        wp_die(); // Should already be handled by wp_send_json_* but good practice
    }

    /**
     * AJAX handler for tracking frontend visits.
     *
     * Receives visit data from frontend JS, retrieves credentials,
     * and forwards the data to the backend API.
     *
     * @since 1.0.0
     */
    public function ajax_sm_track_visit() {
        // 1. Verify Nonce
        check_ajax_referer('sm_visit_tracking_nonce', 'nonce');

        // 2. Get Credentials and Settings
        $api_token = get_option('shopmetrics_analytics_api_token', '');
        $site_identifier = esc_url_raw(site_url());
        $settings = get_option('shopmetrics_settings', []);
        $tracking_enabled = !empty($settings['enable_visit_tracking']);

        // Block if not connected or tracking disabled
        if (empty($api_token) || !$tracking_enabled) {
            wp_send_json_error(array('message' => 'Tracking not enabled or site not connected.'), 403);
            wp_die();
        }

        // 3. Retrieve and Sanitize POST Data
        $page_url = isset($_POST['pageUrl']) ? esc_url_raw(wp_unslash($_POST['pageUrl'])) : '';
        $referrer = isset($_POST['referrer']) ? esc_url_raw(wp_unslash($_POST['referrer'])) : '';
        $session_id = isset($_POST['sessionId']) ? sanitize_text_field(wp_unslash($_POST['sessionId'])) : '';
        $utm_source = isset($_POST['utmSource']) ? sanitize_text_field(wp_unslash($_POST['utmSource'])) : null;
        $utm_medium = isset($_POST['utmMedium']) ? sanitize_text_field(wp_unslash($_POST['utmMedium'])) : null;
        $utm_campaign = isset($_POST['utmCampaign']) ? sanitize_text_field(wp_unslash($_POST['utmCampaign'])) : null;
        $utm_term = isset($_POST['utmTerm']) ? sanitize_text_field(wp_unslash($_POST['utmTerm'])) : null;
        $utm_content = isset($_POST['utmContent']) ? sanitize_text_field(wp_unslash($_POST['utmContent'])) : null;
        $page_type = isset($_POST['pageType']) ? sanitize_key(wp_unslash($_POST['pageType'])) : 'unknown';
        // --- Retrieve and sanitize orderId --- 
        $order_id = isset($_POST['orderId']) ? absint($_POST['orderId']) : null;

        // Basic validation
        if ( empty( $page_url ) || empty( $session_id ) ) {
            wp_send_json_error( array( 'message' => 'Missing required visit data (URL or Session ID).' ), 400 );
            wp_die();
        }

        // 4. Prepare Data Payload for Backend
        $payload = array(
            'page_url'  => $page_url,
            'referrer'  => $referrer,
            'session'   => $session_id,
            'utms' => array_filter(array( // Only include non-null UTMs
                'source'   => $utm_source,
                'medium'   => $utm_medium,
                'campaign' => $utm_campaign,
                'term'     => $utm_term,
                'content'  => $utm_content,
            )),
            'page_type' => $page_type,
            'order_id'  => $order_id // Add order_id (will be null if not set/valid)
        );
        
        // Filter out null values (including order_id if null)
        $payload = array_filter( $payload, function( $value ) { return ! is_null( $value ); } );

        // 5. Call Backend API
        $backend_url = SHOPMETRICS_API_URL . '/v1/visits';
        $headers = array(
            'Content-Type'                  => 'application/json',
            'X-FinanciarMe-Site-Identifier' => $site_identifier,
            'X-FinanciarMe-Token'           => $api_token,
        );
        $args = array(
            'method'  => 'POST',
            'headers' => $headers,
            'timeout' => 15, // Reasonable timeout for a tracking request
            'body'    => json_encode( $payload ),
        );

        \ShopMetrics_Logger::get_instance()->info("ShopMetrics Track Visit: Sending data for $site_identifier: " . json_encode( $payload ) );
        $response = wp_remote_post( $backend_url, $args );

        // 6. Handle Response
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            \ShopMetrics_Logger::get_instance()->error("ShopMetrics Track Visit Error (WP Error) for $site_identifier: $error_message");
            wp_send_json_error( array( 'message' => "Error sending visit data: $error_message" ), 500 );
        } else {
            $response_code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( $response_code >= 200 && $response_code < 300 ) {
                // Success (e.g., 200 OK, 201 Created, 202 Accepted)
                \ShopMetrics_Logger::get_instance()->info("ShopMetrics Track Visit Success for $site_identifier (Code: $response_code)");
                wp_send_json_success( array( 'message' => 'Visit tracked successfully.' ) );
            } else {
                // Failure
                $error_message = isset( $data['message'] ) ? $data['message'] : 'Unknown error from backend.';
                \ShopMetrics_Logger::get_instance()->error("ShopMetrics Track Visit Error (Backend) for $site_identifier: Code $response_code, Message: $error_message, Body: $body");
                wp_send_json_error( array( 'message' => "Failed to track visit (Code: $response_code): $error_message" ), $response_code );
            }
        }

        wp_die(); // End AJAX handler
	}

    /**
     * AJAX handler to auto-detect potential COGS meta keys.
     *
     * @since 1.2.0
     */
    public function ajax_sm_auto_detect_cogs_key() {
        check_ajax_referer( 'sm_settings_ajax_nonce', 'nonce' ); 
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'shopmetrics' ) ), 403 );
        }

        $known_keys = array( '_wc_cog_item_cost', 'cost_of_goods', '_alg_wc_cog_item_cost', '_wjecf_cost_of_goods' );
        $found_key = null;
        $all_order_item_meta_keys = array();

        // Получаем последние 100 заказов
        $orders = wc_get_orders(array(
            'limit' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => array('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-refunded', 'wc-pending'),
        ));
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if (method_exists($item, 'get_meta_data')) {
                    foreach ($item->get_meta_data() as $meta) {
                        $all_order_item_meta_keys[$meta->key] = true;
                    }
                }
            }
        }

        if (empty($all_order_item_meta_keys)) {
            wp_send_json_success( array( 'detected_key' => null, 'message' => __( 'No order item meta keys found in recent orders to scan.', 'shopmetrics' ) ) );
            return;
        }

        // 1. Check against known keys
        foreach ( $known_keys as $known_key ) {
            if ( isset( $all_order_item_meta_keys[ $known_key ] ) ) {
                $found_key = $known_key;
                break;
            }
        }

        // 2. If not found, search for keys containing "cog" or "cost"
        if ( ! $found_key ) {
            foreach ( array_keys( $all_order_item_meta_keys ) as $meta_key_name ) {
                if ( stripos( $meta_key_name, 'cog' ) !== false || stripos( $meta_key_name, 'cost' ) !== false ) {
                    $found_key = $meta_key_name;
                    break; 
                }
            }
        }

        if ( $found_key ) {
            // translators: %s is the detected COGS meta key name
            wp_send_json_success( array( 'detected_key' => $found_key, 'message' => sprintf(__( 'Detected potential COGS key: %s', 'shopmetrics' ), "<code>$found_key</code>" ) ) );
        } else {
            wp_send_json_success( array( 'detected_key' => null, 'message' => __( 'No common COGS meta key found in recent order items. You can try selecting one manually.', 'shopmetrics' ) ) );
        }
    }

    /**
     * AJAX handler to get all distinct product meta keys (for COGS selection).
     *
     * @since 1.2.0
     */
    public function ajax_sm_get_all_meta_keys() {
        check_ajax_referer( 'sm_settings_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'shopmetrics' ) ), 403 );
        }

        $all_order_item_meta_keys = array();
        $orders = wc_get_orders(array(
            'limit' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => array('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-refunded', 'wc-pending'),
        ));
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if (method_exists($item, 'get_meta_data')) {
                    foreach ($item->get_meta_data() as $meta) {
                        $all_order_item_meta_keys[$meta->key] = true;
                    }
                }
            }
        }
        $meta_keys = array_keys($all_order_item_meta_keys);
        sort($meta_keys, SORT_STRING);
        $options = array_map(function($key) {
            return array('value' => $key, 'label' => $key);
        }, $meta_keys);
        array_unshift($options, array('value' => '', 'label' => __( '-- Do not use a meta key --', 'shopmetrics' )));
        array_unshift($options, array('value' => '_placeholder_', 'label' => __( '-- Select a Key --', 'shopmetrics' )));
        wp_send_json_success( array( 'meta_keys' => $options ) );
    }

    /**
     * AJAX handler to manually trigger an inventory snapshot.
     *
     * @since 1.0.0 
     */
    public function ajax_manual_snapshot() {
        // Verify nonce (use the same nonce as settings page general actions for simplicity)
        // Ensure 'sm_settings_ajax_nonce' is available where the button will be.
        check_ajax_referer( 'sm_settings_ajax_nonce', 'nonce' );

        // Check user capability
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'shopmetrics' ) ), 403 );
        }

        // Ensure the snapshotter class is available
        $snapshotter_file = plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-shopmetrics-snapshotter.php';
        if ( ! file_exists( $snapshotter_file ) ) {
            \ShopMetrics_Logger::get_instance()->error("ShopMetrics Admin: Manual snapshot trigger failed. Snapshotter class file not found: " . $snapshotter_file);
            wp_send_json_error( array( 'message' => __( 'Snapshotter component is missing. Cannot trigger snapshot.', 'shopmetrics' ) ), 500 );
            return; // wp_die() is handled by wp_send_json_error
        }
        require_once $snapshotter_file;

        if ( ! class_exists( 'ShopMetrics_Snapshotter' ) || ! method_exists( 'ShopMetrics_Snapshotter', 'take_inventory_snapshot' ) ) {
            \ShopMetrics_Logger::get_instance()->error("ShopMetrics Admin: Manual snapshot trigger failed. ShopMetrics_Snapshotter class or take_inventory_snapshot method not found.");
            wp_send_json_error( array( 'message' => __( 'Snapshotter class or method not found. Cannot trigger snapshot.', 'shopmetrics' ) ), 500 );
            return;
        }

        try {
            \ShopMetrics_Logger::get_instance()->info("ShopMetrics Admin: Manual inventory snapshot initiated by user.");
            
            // Define a constant to indicate manual trigger
            if ( ! defined( 'SM_DOING_MANUAL_SNAPSHOT' ) ) {
                define( 'SM_DOING_MANUAL_SNAPSHOT', true );
            }

            \ShopMetrics_Snapshotter::take_inventory_snapshot();
            
            wp_send_json_success( array( 'message' => __( 'Inventory snapshot process has been triggered.', 'shopmetrics' ) ) );
        } catch ( \Exception $e ) {
            \ShopMetrics_Logger::get_instance()->error("ShopMetrics Admin: Error during manual snapshot trigger: " . $e->getMessage());
            wp_send_json_error( array( 'message' => __( 'An error occurred while triggering the snapshot: ', 'shopmetrics' ) . esc_html($e->getMessage()) ), 500 );
        }
        
        // wp_die(); // This is handled by wp_send_json_success/error
	}

    /**
     * Render the Cart Recovery settings page for this plugin.
     *
     * @since    1.2.0
     */
    public function display_cart_recovery_page() {
        echo '<div class="wrap">';
        // Removed the Cart Recovery Settings header
        
        // Add ajaxurl for vanilla JS
        echo '<script type="text/javascript">
            var ajaxurl = "' . esc_url( admin_url('admin-ajax.php') ) . '";
        </script>';
        
        // Check if cart recovery is enabled in the current plan
        $subscription_status = get_option('shopmetrics_subscription_status', '');
        $is_premium_feature = true;
        $show_upgrade_notice = ($subscription_status !== 'active' && $subscription_status !== 'trial');
        
        // Load settings for the template
        $settings = get_option('shopmetrics_settings', array());
        
        // Explicit global declaration to ensure it's accessible to the template
        $GLOBALS['shopmetricsanalytics_admin'] = $this;
        
        // Backup in case $GLOBALS doesn't work properly
        global $shopmetricsanalytics_admin;
        $shopmetricsanalytics_admin = $this;
        
        // Load the settings form from the template file (template has its own form tag)
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/settings-cart-recovery.php';
        echo '</div>';
    }
    
    /**
     * AJAX handler for sending a test recovery email
     * 
     * @since 1.2.0
     */
    public function ajax_test_recovery_email() {
        // Nonce check for security
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['nonce'])), 'sm_cart_recovery_ajax_nonce' ) ) {
            wp_send_json_error( 'Nonce verification failed!', 403 );
            return;
        }

        // Capability check
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'You do not have permission to perform this action.', 403 );
            return;
        }
        
        // Get the test email from the POST data, fallback to admin email
        $test_email = isset($_POST['email']) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : get_option('admin_email');
        
        // Get the cart recovery class from the main plugin instance
        $cart_recovery = $this->plugin->get_cart_recovery();
        
        if (!$cart_recovery) {
            wp_send_json_error(array('message' => __('Cart recovery component is not available.', 'shopmetrics')), 500);
            return;
        }
        
        // Call the test email method only once
        $result = $cart_recovery->send_test_email($test_email, '', $this->get_test_cart_data());
        
        if ($result) {
            wp_send_json_success(array('message' => sprintf(
                // translators: %s is the email address where the test email was sent
                __('Test email sent to %s successfully.', 'shopmetrics'),
                $test_email
            )));
        } else {
            wp_send_json_error(array('message' => __('Failed to send test email. Check logs for details.', 'shopmetrics')));
        }
    }
    
    /**
     * Get real WooCommerce products for test emails
     * 
     * @return array Cart data with real products or sample products if none exist
     */
    private function get_test_cart_data() {
        // Check if WooCommerce is active
        if (!function_exists('wc_get_products')) {
            // Return sample data if WooCommerce functions aren't available
            return $this->get_sample_cart_data();
        }
        
        // Query for actual products
        $products = wc_get_products(array(
            'limit' => 3,
            'status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        
        // If we have products, use them
        if (!empty($products)) {
            $items = array();
            $total = 0;
            
            foreach ($products as $index => $product) {
                // Get random quantity between 1 and 3
                $quantity = wp_rand(1, 3);
                $price = (float)$product->get_price();
                $subtotal = $price * $quantity;
                $total += $subtotal;
                
                $items[] = array(
                    'product_id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $subtotal,
                    'line_total' => $subtotal,
                );
                
                // Limit to 2 products for the test email
                if ($index >= 1) {
                    break;
                }
            }
            
            return array(
                'items' => $items,
                'total' => $total,
                'cart_hash' => md5('test_cart_' . time()),
            );
        }
        
        // Fall back to sample data if no products were found
        return $this->get_sample_cart_data();
    }
    
    /**
     * Get sample cart data for testing
     * 
     * @return array Sample cart data
     */
    private function get_sample_cart_data() {
        return array(
            'items' => array(
                array(
                    'product_id' => 1,
                    'name' => __('Sample Product 1', 'shopmetrics'),
                    'quantity' => 2,
                    'price' => 19.99,
                    'subtotal' => 39.98,
                    'line_total' => 39.98,
                ),
                array(
                    'product_id' => 2,
                    'name' => __('Sample Product 2', 'shopmetrics'),
                    'quantity' => 1,
                    'price' => 29.99,
                    'subtotal' => 29.99,
                    'line_total' => 29.99,
                ),
            ),
            'total' => 69.97,
            'cart_hash' => md5('sample_cart_' . time()),
        );
    }

    /**
     * AJAX handler to fix the stock snapshot schedule
     *
     * @since 1.0.0
     */
    public function ajax_fix_snapshot_schedule() {
        // Verify nonce (use the same nonce as settings page general actions for simplicity)
        check_ajax_referer( 'sm_settings_ajax_nonce', 'nonce' );

        // Check user capability
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'shopmetrics' ) ), 403 );
        }

        // Ensure the snapshotter class is available
        $snapshotter_file = plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-shopmetrics-snapshotter.php';
        if ( ! file_exists( $snapshotter_file ) ) {
            \ShopMetrics_Logger::get_instance()->error("ShopMetrics Admin: Fix schedule failed. Snapshotter class file not found: " . $snapshotter_file);
            wp_send_json_error( array( 'message' => __( 'Snapshotter component is missing.', 'shopmetrics' ) ), 500 );
            return;
        }
        require_once $snapshotter_file;

        if ( ! class_exists( 'ShopMetrics_Snapshotter' ) ) {
            \ShopMetrics_Logger::get_instance()->error("ShopMetrics Admin: Fix schedule failed. ShopMetrics_Snapshotter class not found.");
            wp_send_json_error( array( 'message' => __( 'Snapshotter class not found.', 'shopmetrics' ) ), 500 );
            return;
        }

        try {
            \ShopMetrics_Logger::get_instance()->info("ShopMetrics Admin: Attempting to fix snapshot schedule.");
            
            // Check if there's already a scheduled event
            $next_scheduled = as_next_scheduled_action('shopmetrics_analytics_take_inventory_snapshot');
            
            // If there's an existing schedule, unschedule it first
            if ($next_scheduled) {
                as_unschedule_all_actions('shopmetrics_analytics_take_inventory_snapshot');
                \ShopMetrics_Logger::get_instance()->info("ShopMetrics Admin: Unscheduled existing snapshot action");
            }
            
            // Schedule a new action
            $timestamp = strtotime('tomorrow 2am');
            $result = as_schedule_recurring_action($timestamp, DAY_IN_SECONDS, 'shopmetrics_analytics_take_inventory_snapshot', array(), 'ShopMetrics');
            
            if ($result) {
                \ShopMetrics_Logger::get_instance()->info("ShopMetrics Admin: Successfully rescheduled snapshot action for " . gmdate('Y-m-d H:i:s', $timestamp));
                
                // Get the new schedule time for display
                $next_scheduled = as_next_scheduled_action('shopmetrics_analytics_take_inventory_snapshot');
                $formatted_time = wp_date(get_option('date_format') . ' ' . get_option('time_format'), $next_scheduled);
                
                wp_send_json_success(array(
                    'message' => __('Snapshot schedule has been fixed.', 'shopmetrics'),
                    'next_snapshot' => $formatted_time
                ));
            } else {
                \ShopMetrics_Logger::get_instance()->error("ShopMetrics Admin: Failed to reschedule snapshot action");
                wp_send_json_error(array('message' => __('Failed to reschedule the snapshot. Please check server logs.', 'shopmetrics')));
            }
        } catch (\Exception $e) {
            \ShopMetrics_Logger::get_instance()->error("ShopMetrics Admin: Error fixing snapshot schedule: " . $e->getMessage());
            wp_send_json_error(array('message' => __('An error occurred: ', 'shopmetrics') . esc_html($e->getMessage())));
        }
    }

    /**
     * Check for and fix multiple scheduled snapshot actions
     * 
     * This method is run during plugin initialization to ensure there are no duplicate
     * scheduled snapshot actions that could cause duplicate emails or processing.
     * 
     * @since 1.4.0
     */
    public function check_snapshot_schedule() {
        // Only proceed if Action Scheduler is available - need to check both the class and function
        if (!class_exists('ActionScheduler_Store') || !function_exists('as_get_scheduled_actions')) {
            \ShopMetrics_Logger::get_instance()->warn("ShopMetrics Admin: Action Scheduler not fully available. Skipping schedule check.");
            return;
        }

        try {
            // Get all pending snapshot actions
            $scheduled_actions = as_get_scheduled_actions([
                'hook' => 'shopmetrics_analytics_take_inventory_snapshot',
                'status' => \ActionScheduler_Store::STATUS_PENDING
            ]);

            // If more than one action is scheduled, fix it
            if (count($scheduled_actions) > 1) {
                \ShopMetrics_Logger::get_instance()->info("ShopMetrics Admin: Found " . count($scheduled_actions) . ' duplicate snapshot actions. Fixing schedule.');
                
                // Unschedule all existing actions
                as_unschedule_all_actions('shopmetrics_analytics_take_inventory_snapshot');
                
                // Schedule a new single action at the appropriate time
                $timestamp = strtotime('tomorrow 2am');
                $result = as_schedule_recurring_action($timestamp, DAY_IN_SECONDS, 'shopmetrics_analytics_take_inventory_snapshot', array(), 'ShopMetrics');
                
                if ($result) {
                    \ShopMetrics_Logger::get_instance()->info("ShopMetrics Admin: Successfully fixed duplicate snapshot schedule.");
                } else {
                    \ShopMetrics_Logger::get_instance()->error("ShopMetrics Admin: Failed to reschedule snapshot action after fixing duplicates.");
                }
            }
        } catch (\Exception $e) {
            \ShopMetrics_Logger::get_instance()->error("ShopMetrics Admin: Error checking snapshot schedule: " . $e->getMessage());
        }
    }

    /**
     * Render the Data Sources page for this plugin.
     *
     * @since    1.3.0
     */
    public function display_data_sources_page() {
        echo '<div class="wrap">';
        
        // Check if the plugin is connected
        $api_token = get_option('shopmetrics_analytics_api_token', '');
        $site_identifier = esc_url_raw(site_url());
        
        if (empty($api_token)) {
            // Not connected - prompt to connect first
            echo '<div class="notice notice-warning">';
            echo '<p>' . esc_html__('Please connect your site to ShopMetrics before configuring data sources.', 'shopmetrics') . '</p>';
            echo '<p><a href="' . esc_url(admin_url('admin.php?page=' . $this->plugin_name . '-settings')) . '" class="button button-primary">' . 
                esc_html__('Go to Settings', 'shopmetrics') . '</a></p>';
            echo '</div>';
            echo '</div>'; // Close .wrap
            return;
        }
        
        // Explicit global declaration to ensure it's accessible to the template
        $GLOBALS['shopmetricsanalytics_admin'] = $this;
        
        // Backup in case $GLOBALS doesn't work properly
        global $shopmetricsanalytics_admin;
        $shopmetricsanalytics_admin = $this;
        
        // Include the data sources template file
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/settings-data-sources.php';
        
        echo '</div>'; // Close .wrap
    }

    /**
     * Render the Subscription page for this plugin.
     *
     * @since    1.3.0
     */
    public function display_subscription_page() {
        echo '<div class="wrap">';
        
        // Check if the plugin is connected
        $api_token = get_option('shopmetrics_analytics_api_token', '');
        $site_identifier = esc_url_raw(site_url());
        
        if (empty($api_token)) {
            // Not connected - prompt to connect first
            echo '<div class="notice notice-warning">';
            echo '<p>' . esc_html__('Please connect your site to ShopMetrics before managing your subscription.', 'shopmetrics') . '</p>';
            echo '<p><a href="' . esc_url(admin_url('admin.php?page=' . $this->plugin_name . '-settings')) . '" class="button button-primary">' . 
                esc_html__('Go to Settings', 'shopmetrics') . '</a></p>';
            echo '</div>';
            echo '</div>'; // Close .wrap
            return;
        }

        // Handle Stripe checkout return
        if (isset($_GET['stripe_checkout'])) {
            $checkout_status = sanitize_text_field( wp_unslash( $_GET['stripe_checkout'] ) );
            
            if ($checkout_status === 'success') {
                // Automatically sync subscription status after successful payment
                $this->maybe_sync_subscription_data($site_identifier, $api_token);
                
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>' . esc_html__('Payment successful! Your subscription has been activated.', 'shopmetrics') . '</p>';
                echo '</div>';
            } elseif ($checkout_status === 'cancel') {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p>' . esc_html__('Payment was cancelled. You can try again anytime.', 'shopmetrics') . '</p>';
                echo '</div>';
            }
        }
        
        // Always fetch current subscription status from backend before displaying page
        $this->fetch_current_subscription_status($site_identifier, $api_token);
        
        // Explicit global declaration to ensure it's accessible to the template
        $GLOBALS['shopmetricsanalytics_admin'] = $this;
        
        // Backup in case $GLOBALS doesn't work properly
        global $shopmetricsanalytics_admin;
        $shopmetricsanalytics_admin = $this;
        
        // Include the subscription template file
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/settings-subscription.php';
        
        echo '</div>'; // Close .wrap
    }

	/**
	 * AJAX handler for auto-detecting the best order blocks option.
	 *
	 * Analyzes the WooCommerce orders from the past 3 months to recommend
	 * an appropriate number of order blocks for the user's needs.
	 *
	 * @since 1.3.0
	 */
	public function ajax_auto_detect_order_blocks() {
		// Verify nonce and capability
		check_ajax_referer('sm_settings_ajax_nonce', 'nonce');
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Insufficient permissions.', 'shopmetrics')), 403);
			return;
		}
		
		// Make sure WooCommerce is active
		if (!class_exists('WooCommerce')) {
			wp_send_json_error(array(
				'message' => __('WooCommerce is not active. Cannot analyze order history.', 'shopmetrics'),
				'recommended_blocks' => 1
			));
			return;
		}
		
		// Get orders from the past 3 months
		$three_months_ago = strtotime('-3 months');
		$args = array(
			'status' => array('wc-completed', 'wc-processing'),
			'date_created' => '>' . $three_months_ago,
			'paginate' => true,
			'limit' => -1 // Get all orders, we need the count
		);
		
		$orders = wc_get_orders($args);
		$order_count = is_array($orders) ? count($orders) : $orders->total;
		
		// Calculate monthly average
		$monthly_avg = ceil($order_count / 3);
		
		// Add 20% buffer to account for fluctuations
		$monthly_avg_with_buffer = ceil($monthly_avg * 1.2);
		
		// Determine recommended blocks (1 block = 100 orders)
		$recommended_blocks = max(1, ceil($monthly_avg_with_buffer / 100));
		
		// Cap at 10 blocks for premium plan
		if ($recommended_blocks > 10) {
			wp_send_json_success(array(
				'recommended_blocks' => 10,
				// translators: %1$d is the monthly order average, %2$d is the monthly average with buffer
				'message' => sprintf(__('Your store processes approximately %1$d orders per month (with 20%% buffer: %2$d). This exceeds the Premium Plan limit. Consider contacting sales for an Enterprise Plan.', 'shopmetrics'),
					$monthly_avg,
					$monthly_avg_with_buffer
				)
			));
			return;
		}
		
		// Return the recommendation
		wp_send_json_success(array(
			'recommended_blocks' => $recommended_blocks,
			'orders_per_month' => $monthly_avg,
			'orders_with_buffer' => $monthly_avg_with_buffer,
			// translators: %1$d is the monthly order average, %2$d is the monthly average with buffer, %3$d is the recommended order blocks
			'message' => sprintf(__('Based on your order history (average %1$d orders per month, with 20%% buffer: %2$d), we recommend %3$d order blocks for your subscription.', 'shopmetrics'),
				$monthly_avg,
				$monthly_avg_with_buffer,
				$recommended_blocks
			)
		));
	}

    /**
     * Get or create an instance of the Cart_Recovery class
     * 
     * @return Cart_Recovery|false Cart recovery instance or false on failure
     */


    /**
     * Render the COGS Meta Key detection functionality in the data sources page
     * 
     * @since 1.2.0 
     */
    public function render_data_sources_cogs_fields() {
        $api_token = get_option('shopmetrics_analytics_api_token', '');
        $site_identifier = get_option('shopmetrics_analytics_site_identifier', '');
        
        // Add hidden input fields for preserving connection data
        echo '<input type="hidden" name="shopmetrics_analytics_api_token" value="' . esc_attr($api_token) . '" />';
        echo '<input type="hidden" name="shopmetrics_analytics_site_identifier" value="' . esc_attr($site_identifier) . '" />';
    }

	/**
	 * Add a jQuery patch in the head section to prevent DOM structure errors
	 * This uses admin_print_scripts hook to run very early before other scripts
	 */
	public function patch_jquery_early() {
		// Only run on our plugin admin pages - first check if the function exists
		if (!function_exists('get_current_screen')) {
			return;
		}
		
		$screen = \get_current_screen();
		if (!$screen || strpos($screen->id, $this->plugin_name) === false) {
			return;
		}
		
		?>
		<script type="text/javascript">
		(function() {			
			// Function to patch jQuery once it's available
			function patchjQuery() {
				if (typeof jQuery !== 'undefined') {
					var $ = jQuery;
					
					// Save original methods
					var origInsertBefore = $.fn.insertBefore;
					var origAppend = $.fn.append;
					var origPrepend = $.fn.prepend;
					var origAfter = $.fn.after;
					var origBefore = $.fn.before;
					
					// Helper to check for circular references
					function wouldCreateCircular($this, $target) {
						var circular = false;
						$this.each(function() {
							var elem = this;
							$target.each(function() {
								if (elem === this || $.contains(elem, this)) {
									circular = true;
									return false;
								}
							});
							if (circular) return false;
						});
						return circular;
					}
					
					// Patch insertBefore
					$.fn.insertBefore = function(target) {
						var $target = $(target);
						if (wouldCreateCircular(this, $target)) {
							console.warn('Prevented circular DOM reference in insertBefore');
							return this;
						}
						return origInsertBefore.apply(this, arguments);
					};
					
					// Patch append
					$.fn.append = function(content) {
						var $content = $(content);
						if (content && content.nodeType && wouldCreateCircular($content, this)) {
							console.warn('Prevented circular DOM reference in append');
							return this;
						}
						return origAppend.apply(this, arguments);
					};
					
					// Patch prepend
					$.fn.prepend = function(content) {
						var $content = $(content);
						if (content && content.nodeType && wouldCreateCircular($content, this)) {
							console.warn('Prevented circular DOM reference in prepend');
							return this;
						}
						return origPrepend.apply(this, arguments);
					};
					
					// Patch after
					$.fn.after = function(content) {
						var $content = $(content);
						if (content && content.nodeType && wouldCreateCircular($content, this)) {
							console.warn('Prevented circular DOM reference in after');
							return this;
						}
						return origAfter.apply(this, arguments);
					};
					
					// Patch before
					$.fn.before = function(content) {
						var $content = $(content);
						if (content && content.nodeType && wouldCreateCircular($content, this)) {
							console.warn('Prevented circular DOM reference in before');
							return this;
						}
						return origBefore.apply(this, arguments);
					};
					
					// Clear interval once patched
					clearInterval(patchInterval);
				}
			}
			
			// Try to patch immediately if jQuery is already loaded
			if (typeof jQuery !== 'undefined') {
				patchjQuery();
			} else {
				// Set up interval to keep trying until jQuery is available
				var patchInterval = setInterval(patchjQuery, 50);
			}
		})();
		</script>
		<?php
	}

    /**
     * AJAX handler for saving a plugin setting
     * 
     * This is used by the onboarding wizard to save settings via AJAX
     */
    public function ajax_save_setting() {
        // Check nonce for security
        if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_ajax_nonce'])), 'sm_settings_ajax_nonce')) {
            wp_send_json_error(['message' => 'Invalid security token. Please refresh the page and try again.'], 403);
            exit;
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'You do not have permission to change settings.'], 403);
            exit;
        }

        // Get and validate the setting name and value
        $setting_name = isset($_POST['setting_name']) ? sanitize_text_field( wp_unslash( $_POST['setting_name'] ) ) : '';
        $setting_value = isset($_POST['setting_value']) ? sanitize_text_field( wp_unslash( $_POST['setting_value'] ) ) : '';

        if (empty($setting_name)) {
            wp_send_json_error(['message' => 'Setting name is required.'], 400);
            exit;
        }

        // Map the setting name to the actual option name in WordPress
        $option_map = [
            'api_token' => 'shopmetrics_analytics_api_token',
            'site_identifier' => 'shopmetrics_analytics_site_identifier',
            'cogs_meta_key' => 'shopmetrics_settings[cogs_meta_key]',
            'cogs_default_percentage' => 'shopmetrics_settings[cogs_default_percentage]',
            'onboarding_completed' => 'shopmetrics_needs_onboarding'
        ];

        if (!isset($option_map[$setting_name])) {
            wp_send_json_error(['message' => 'Invalid setting name.'], 400);
            exit;
        }

        $option_name = $option_map[$setting_name];

        // Special handling for onboarding_completed
        if ($setting_name === 'onboarding_completed' && $setting_value === 'true') {
            // Set the onboarding flag to false since it's completed
            update_option($option_name, 'false');
            wp_send_json_success(['message' => 'Onboarding marked as completed.']);
            exit;
        }

        // Perform additional validation based on the setting name
        switch ($setting_name) {
            case 'cogs_default_percentage':
                // Ensure it's a valid percentage (0-100)
                $percentage = floatval($setting_value);
                if ($percentage < 0 || $percentage > 100) {
                    wp_send_json_error(['message' => 'Default COGS percentage must be between 0 and 100.'], 400);
                    exit;
                }
                break;

            case 'api_token':
            case 'site_identifier':
                // Ensure these are not empty
                if (empty($setting_value)) {
                    wp_send_json_error(['message' => 'This setting cannot be empty.'], 400);
                    exit;
                }
                break;
        }

        // Save the setting
        $result = update_option($option_name, $setting_value);

        if ($result) {
            wp_send_json_success(['message' => 'Setting saved successfully.']);
        } else {
            wp_send_json_error(['message' => 'Failed to save setting. The value might be unchanged.']);
        }
    }

    /**
     * AJAX handler for syncing subscription status from the backend API.
     *
     * @since 1.3.0
     */
    // Removed: ajax_sync_subscription - status now updates automatically

    /**
     * AJAX handler for retrieving the current progress of historical sync.
     *
     * @since 1.2.0
     */
    public function ajax_get_sync_progress() {
        // Verify nonce
        if ( ! isset( $_POST['_ajax_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ) ), 'shopmetrics_api_actions' ) ) {
            \ShopMetrics_Logger::get_instance()->warn("Progress AJAX: Nonce verification failed");
            wp_send_json_error( array( 'message' => __( 'Nonce verification failed!', 'shopmetrics' ) ), 403 );
        }

        // Check user capability
        if ( ! current_user_can( 'manage_options' ) ) {
            \ShopMetrics_Logger::get_instance()->warn("Progress AJAX: User does not have manage_options capability");
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'shopmetrics' ) ), 403 );
        }
        
        $progress_data = get_option( 'sm_historical_sync_progress', '' );
        \ShopMetrics_Logger::get_instance()->debug("Progress AJAX: Raw progress data from option: " . wp_json_encode($progress_data));
        
        $progress = !empty($progress_data) ? json_decode($progress_data, true) : null;
        
        if ( !$progress ) {
            \ShopMetrics_Logger::get_instance()->warn("Progress AJAX: No valid progress data found");
            $progress = [
                'status' => 'none',
                'progress' => 0,
                'message' => __( 'No synchronization has been initiated.', 'shopmetrics' )
            ];
        } else {
            // \ShopMetrics_Logger::get_instance()->debug("Progress AJAX: Decoded progress data: " . print_r($progress, true));
        }
        
        // Check if sync is stale (no updates for more than 5 minutes)
        if ( $progress['status'] === 'in_progress' && isset($progress['timestamp']) ) {
            $time_since_update = time() - intval($progress['timestamp']);
            \ShopMetrics_Logger::get_instance()->debug("Progress AJAX: Time since last update: " . $time_since_update . " seconds");
            
            if ( $time_since_update > 300 ) { // 5 minutes
                \ShopMetrics_Logger::get_instance()->warn("Progress AJAX: Sync appears stalled (no updates for > 5 minutes)");
                $progress['status'] = 'stalled';
                $progress['message'] = __( 'Synchronization appears to have stalled.', 'shopmetrics' );
                // Update the stored progress
                update_option( 'sm_historical_sync_progress', json_encode($progress) );
            }
        }
        
        // Check if we should continue the sync via AJAX
        if ($progress['status'] === 'in_progress' || $progress['status'] === 'starting') {
            // Check for failsafe trigger or if no next action is scheduled
            $should_continue = false;
            
            // Check if we have the fallback flag set
            if (get_transient('sm_sync_fallback_required') === 'yes') {
                \ShopMetrics_Logger::get_instance()->info("Progress AJAX: Fallback flag detected, will trigger continuation");
                $should_continue = true;
                delete_transient('sm_sync_fallback_required'); // Clear the flag
            }
            
            // Check if there's no scheduled action
            if (!$should_continue && function_exists('as_next_scheduled_action')) {
                $next_run = as_next_scheduled_action('shopmetrics_analytics_sync_historical_orders');
                if (!$next_run) {
                    \ShopMetrics_Logger::get_instance()->info("Progress AJAX: No scheduled action found, will trigger continuation");
                    $should_continue = true;
                }
            }
            
            // If last update was more than 15 seconds ago, trigger continuation as a backup
            if (!$should_continue && isset($progress['timestamp']) && (time() - intval($progress['timestamp']) > 15)) {
                \ShopMetrics_Logger::get_instance()->info("Progress AJAX: Last update was more than 15 seconds ago, triggering continuation as backup");
                $should_continue = true;
            }
            
            // Continue the sync if needed
            if ($should_continue) {
                \ShopMetrics_Logger::get_instance()->info("Progress AJAX: Continuing sync via AJAX trigger");
                
                // Get the Orders_Tracker class and continue the sync
                $orders_tracker_file = plugin_dir_path(dirname(__FILE__)) . 'includes/class-shopmetrics-orders-tracker.php';
                
                if (file_exists($orders_tracker_file)) {
                    if (!class_exists('ShopMetrics\\Analytics\\Orders_Tracker')) {
                        require_once($orders_tracker_file);
                    }
                    
                    // Execute the action directly 
                    do_action('shopmetrics_analytics_sync_historical_orders');
                    
                    // Get updated progress data after continuation
                    $updated_progress_data = get_option('sm_historical_sync_progress', '');
                    if (!empty($updated_progress_data)) {
                        $progress = json_decode($updated_progress_data, true);
                        \ShopMetrics_Logger::get_instance()->debug("Progress AJAX: Progress data updated after continuation");
                    }
                } else {
                    \ShopMetrics_Logger::get_instance()->error("Progress AJAX: Orders_Tracker file not found for AJAX continuation");
                }
            }
        }
        
        // \ShopMetrics_Logger::get_instance()->debug("Progress AJAX: Sending response: " . print_r($progress, true));
        wp_send_json_success( $progress );
    }

    /**
     * AJAX handler for resetting the progress of historical sync.
     *
     * @since 1.2.0
     */
    public function ajax_reset_sync_progress() {
        // Verify nonce - accept both nonce types for maximum compatibility
        $nonce_verified = false;
        
        if (isset($_POST['_ajax_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_ajax_nonce'])), 'shopmetrics_api_actions')) {
            $nonce_verified = true;
        } elseif (isset($_POST['_ajax_nonce']) && check_ajax_referer('shopmetrics_sync_nonce', '_ajax_nonce', false)) {
            $nonce_verified = true;
        }
        
        if (!$nonce_verified) {
            \ShopMetrics_Logger::get_instance()->warn("Reset Progress AJAX: Nonce verification failed");
            wp_send_json_error(array('message' => __('Security verification failed!', 'shopmetrics')), 403);
            return;
        }

        // Check user capability
        if (!current_user_can('manage_options')) {
            \ShopMetrics_Logger::get_instance()->warn("Reset Progress AJAX: User does not have manage_options capability");
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'shopmetrics')), 403);
            return;
        }
        
        // Get the force reset parameter (default false)
        $force_reset = isset($_POST['force_reset']) ? filter_var( wp_unslash( $_POST['force_reset'] ), FILTER_VALIDATE_BOOLEAN) : false;
        
        \ShopMetrics_Logger::get_instance()->info("Reset Progress AJAX: Processing reset request, force_reset=" . ($force_reset ? 'true' : 'false'));
        
        // Check if sync is in progress and not forcing reset
        if (!$force_reset) {
            $progress_data = get_option('sm_historical_sync_progress', '');
            
            if (is_string($progress_data) && !empty($progress_data)) {
                $progress = json_decode($progress_data, true);
                
                if (is_array($progress) && isset($progress['status']) && 
                     $progress['status'] === 'in_progress' && 
                     isset($progress['timestamp']) && 
                     (time() - intval($progress['timestamp'])) < 120 // Less than 2 minutes old (reduced from 5 minutes)
                ) {
                    \ShopMetrics_Logger::get_instance()->info("Reset Progress AJAX: Sync in progress and not forcing reset, aborting");
                    wp_send_json_error(array(
                        'message' => __('Sync is currently in progress. Use force reset if you want to override this.', 'shopmetrics'),
                        'progress' => $progress
                    ), 409);
                    return;
                }
            }
        }
        
        // Reset the progress data to a clean state
        $reset_result = update_option('sm_historical_sync_progress', json_encode([
            'status' => 'reset',
            'progress' => 0,
            'processed_orders' => 0,
            'total_orders' => 0,
            'last_synced_id' => 0,
            'timestamp' => time()
        ]));
        
        // Also clean up any synced flags from orders if force_complete_reset is provided
        if ($force_reset) {
            // Use WordPress API instead of direct database query
            $args = array(
                'meta_query' => array(
                    array(
                        'key' => '_shopmetrics_synced',
                        'compare' => 'EXISTS'
                    )
                ),
                'limit' => -1,
                'return' => 'ids'
            );
            
            $orders_with_meta = wc_get_orders($args);
            if (is_object($orders_with_meta) && isset($orders_with_meta->orders)) {
                $orders_with_meta = $orders_with_meta->orders;
            }
            
            $deleted = 0;
            if (!empty($orders_with_meta)) {
                foreach ($orders_with_meta as $order_id) {
                    $order = wc_get_order($order_id);
                    if ($order) {
                        $order->delete_meta_data('_shopmetrics_synced');
                        $order->save();
                        $deleted++;
                    }
                }
            }
            
            \ShopMetrics_Logger::get_instance()->info("Reset Progress AJAX: Forced complete reset - cleared {$deleted} _shopmetrics_synced meta keys using WooCommerce API");
        }
        
        // Also clean up any scheduled actions
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions('shopmetrics_analytics_sync_historical_orders');
            \ShopMetrics_Logger::get_instance()->info('Reset Progress AJAX: Unscheduled all sync actions after reset');
            
            // Also remove any potentially active or pending actions from the Action Scheduler
            if (class_exists('ActionScheduler_Store')) {
                // Get all pending or running sync actions
                $scheduled_actions = as_get_scheduled_actions( array(
                    'hook' => 'shopmetrics_analytics_sync_historical_orders',
                    'status' => array( \ActionScheduler_Store::STATUS_PENDING, \ActionScheduler_Store::STATUS_RUNNING ),
                ), 'ids' );
                
                // Force mark all of them as complete
                if (!empty($scheduled_actions)) {
                    foreach ($scheduled_actions as $action_id) {
                        if (function_exists('as_mark_complete_action')) {
                            as_mark_complete_action($action_id);
                            \ShopMetrics_Logger::get_instance()->debug("Reset Progress AJAX: Marked action ID $action_id as complete");
                        }
                    }
                }
            }
            
            // Delete any temporary flags that might be preventing new syncs
            delete_option('sm_sync_in_progress_lock');
            delete_option('sm_sync_last_run'); 
            delete_transient('sm_sync_running');
            
            // Add a small delay to ensure all actions are properly cleaned up
            sleep(1);
        }
        
        if ($reset_result) {
            \ShopMetrics_Logger::get_instance()->info('Reset Progress AJAX: Successfully reset sync progress');
            wp_send_json_success(array(
                'message' => __('Sync progress reset successfully.', 'shopmetrics'),
                'force_reset' => $force_reset
            ));
        } else {
            \ShopMetrics_Logger::get_instance()->error('Reset Progress AJAX: Failed to reset sync progress');
            wp_send_json_error(array('message' => __('Failed to reset sync progress.', 'shopmetrics')), 500);
        }
    }

    /**
     * Renders the sync historical data button and progress UI in the admin interface.
     * 
     * @since 1.2.0
     */
    
    /**
     * AJAX handler for clearing logs
     */
    public function ajax_clear_logs() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sm_settings_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Include the logger class if not already included
        if (!class_exists('ShopMetrics_Logger')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-shopmetrics-logger.php';
        }
        
        $logger = \ShopMetrics_Logger::get_instance();
        $result = $logger->clear_logs();
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Logs cleared successfully',
                'fileSize' => $logger->get_log_file_size()
            ));
        } else {
            wp_send_json_error('Failed to clear logs');
        }
    }
    
    /**
     * AJAX handler for downloading logs
     */
    public function ajax_download_logs() {
        // Verify nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'sm_settings_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Include the logger class if not already included
        if (!class_exists('ShopMetrics_Logger')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-shopmetrics-logger.php';
        }
        
        $logger = \ShopMetrics_Logger::get_instance();
        $log_file = $logger->get_log_file();
        
        if (file_exists($log_file)) {
            // Use WordPress filesystem for reading the file
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }
            
            $file_contents = $wp_filesystem->get_contents($log_file);
            if ($file_contents !== false) {
            header('Content-Type: text/plain');
                header('Content-Disposition: attachment; filename="shopmetrics-' . gmdate('Y-m-d-H-i-s') . '.log"');
                header('Content-Length: ' . strlen($file_contents));
                echo $file_contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
            } else {
                wp_die('Unable to read log file');
            }
        } else {
            wp_die('Log file not found');
        }
    }
    
    /**
     * AJAX handler for testing PHP errors
     */
    public function handle_ajax_test_php_error() {
        // Verify nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'shopmetrics_nonce')) {
            wp_die('Security check failed');
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        try {
            \ShopMetrics_Logger::get_instance()->debug('ShopMetrics Debug: Calling send_test_error()');
            $result = \ShopMetrics\Analytics\ShopMetrics_Analytics::send_test_error();
            \ShopMetrics_Logger::get_instance()->debug('ShopMetrics Debug: send_test_error result: ' . wp_json_encode($result));
            
            wp_send_json_success(array(
                'message' => 'Test PHP error sent successfully',
                'result' => $result
            ));
        } catch (Exception $e) {
            \ShopMetrics_Logger::get_instance()->error('ShopMetrics Debug: Exception in send_test_error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Error sending test error: ' . $e->getMessage()
            ));
        }
    }

    /**
     * AJAX handler for testing error scenarios
     */
    public function handle_ajax_test_error_scenarios() {
        // Verify nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'shopmetrics_nonce')) {
            wp_die('Security check failed');
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Ensure Analytics class is loaded
        if (!class_exists('ShopMetrics_Analytics')) {
            $analytics_file = plugin_dir_path(dirname(__FILE__)) . 'includes/class-shopmetrics-analytics.php';
            if (file_exists($analytics_file)) {
                require_once $analytics_file;
            }
        }

        // Check if class exists after loading
        if (!class_exists('ShopMetrics_Analytics')) {
            wp_send_json_error(array(
                'message' => 'Analytics class not found'
            ));
            return;
        }

        try {
            $result = \ShopMetrics\Analytics\ShopMetrics_Analytics::test_error_scenarios();
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Error during error scenario testing: ' . $e->getMessage()
            ));
        }
    }

    /**
     * AJAX handler for rotating logs
     */
    public function ajax_rotate_logs() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sm_settings_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Include the logger class if not already included
        if (!class_exists('ShopMetrics_Logger')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-shopmetrics-logger.php';
        }
        
        $logger = \ShopMetrics_Logger::get_instance();
        $result = $logger->rotate_logs();
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Logs rotated successfully',
                'fileSize' => $logger->get_log_file_size()
            ));
        } else {
            wp_send_json_error('Failed to rotate logs');
        }
    }

    /**
     * AJAX handler for saving analytics consent
     */
    public function ajax_save_analytics_consent() {
        // Verify nonce
        if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_ajax_nonce'])), 'shopmetrics_save_analytics_consent')) {
            wp_send_json_error('Security check failed', 403);
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
            return;
        }
        
        // Get consent value
        $consent = isset($_POST['consent']) ? (int) sanitize_text_field(wp_unslash($_POST['consent'])) : 0;
        $consent = $consent ? 1 : 0; // Ensure it's 0 or 1
        
        // Get current settings
        $settings = get_option('shopmetrics_settings', []);
        
        // Update analytics consent
        $settings['analytics_consent'] = $consent;
        
        // Save settings
        $updated = update_option('shopmetrics_settings', $settings);
        
        if ($updated || get_option('shopmetrics_settings')['analytics_consent'] == $consent) {
            \ShopMetrics_Logger::get_instance()->info("Analytics consent updated to: " . ($consent ? 'enabled' : 'disabled'));
            wp_send_json_success([
                'message' => $consent ? 
                    __('Analytics consent enabled successfully', 'shopmetrics') : 
                    __('Analytics consent disabled successfully', 'shopmetrics'),
                'consent' => $consent
            ]);
        } else {
            \ShopMetrics_Logger::get_instance()->error("Failed to update analytics consent");
            wp_send_json_error(__('Failed to save analytics consent', 'shopmetrics'), 500);
        }
    }

    /**
     * AJAX handler for resetting onboarding wizard
     */
    public function ajax_reset_onboarding() {
        // Verify nonce
        if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_ajax_nonce'])), 'shopmetrics_reset_onboarding')) {
            wp_send_json_error('Security check failed', 403);
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
            return;
        }
        
        try {
            // Reset onboarding flag to require onboarding
            $updated = update_option('shopmetrics_needs_onboarding', 'true');
            
            // Also clear any saved onboarding progress from localStorage (will be handled by frontend)
            // and reset any onboarding-related transients
            delete_transient('shopmetrics_onboarding_progress');
            
            if ($updated || get_option('shopmetrics_needs_onboarding') === 'true') {
                \ShopMetrics_Logger::get_instance()->info("Onboarding wizard reset successfully");
                wp_send_json_success([
                    'message' => __('Onboarding wizard reset successfully', 'shopmetrics'),
                    'redirect_url' => admin_url('admin.php?page=shopmetrics')
                ]);
            } else {
                \ShopMetrics_Logger::get_instance()->error("Failed to reset onboarding wizard");
                wp_send_json_error(__('Failed to reset onboarding wizard', 'shopmetrics'), 500);
            }
        } catch (Exception $e) {
            \ShopMetrics_Logger::get_instance()->error("Exception resetting onboarding: " . $e->getMessage());
            wp_send_json_error(__('Error resetting onboarding: ', 'shopmetrics') . $e->getMessage(), 500);
        }
    }
    
    public function render_sync_historical_data_ui() {
        $api_token = get_option('shopmetrics_analytics_api_token', '');
        $site_identifier = get_option('shopmetrics_analytics_site_identifier', '');
        $is_connected = !empty($api_token) && !empty($site_identifier);
        
        if (!$is_connected) {
            echo '<p class="description">' . esc_html__('Connect your site to enable historical data synchronization.', 'shopmetrics') . '</p>';
            return;
        }
        
        // Generate nonces for both compatibility with older code and new implementation
        $api_actions_nonce = wp_create_nonce('shopmetrics_api_actions');
        $sync_nonce = wp_create_nonce('shopmetrics_sync_nonce');
        
        echo '<div class="sm-sync-button-container">';
        echo '<button id="shopmetrics_start_sync" class="button button-primary" ';
        echo 'data-nonce="' . esc_attr($api_actions_nonce) . '" ';
        echo 'data-sync-nonce="' . esc_attr($sync_nonce) . '">';
        echo esc_html__('Sync Historical Data', 'shopmetrics');
        echo '</button>';
        
        echo '<div class="sm-sync-description" style="margin-top: 10px;">';
        echo '<p class="description">' . esc_html__('This will synchronize orders from the past year with ShopMetrics. The process will run in the background and may take some time depending on the number of orders.', 'shopmetrics') . '</p>';
        echo '</div>';
        
        echo '</div>';
    }

    /**
     * AJAX handler for testing order sync connectivity
     */
    public function ajax_test_order_sync() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sm_settings_ajax_nonce')) {
            wp_send_json_error('Security check failed', 403);
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
            return;
        }
        
        // Get order sync manager instance
        $plugin = \ShopMetrics\Analytics\ShopMetrics::get_instance();
        $order_sync_manager = $plugin->get_order_sync_manager();
        
        if (!$order_sync_manager) {
            wp_send_json_error('Order sync manager not available', 500);
            return;
        }
        
        try {
            $result = $order_sync_manager->test_sync_connectivity();
            
            if ($result['success']) {
                wp_send_json_success([
                    'message' => 'Order sync connectivity test passed',
                    'details' => $result['details']
                ]);
            } else {
                wp_send_json_error($result['message'], 500);
            }
        } catch (Exception $e) {
            wp_send_json_error('Failed to test order sync connectivity: ' . $e->getMessage(), 500);
        }
    }

    /**
     * AJAX handler for checking order sync status
     */
    public function ajax_check_order_sync() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sm_settings_ajax_nonce')) {
            wp_send_json_error('Security check failed', 403);
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
            return;
        }
        
        // Get order sync manager instance
        $plugin = \ShopMetrics\Analytics\ShopMetrics::get_instance();
        $order_sync_manager = $plugin->get_order_sync_manager();
        
        if (!$order_sync_manager) {
            wp_send_json_error('Order sync manager not available', 500);
            return;
        }
        
        try {
            $status = $order_sync_manager->get_sync_status();
            wp_send_json_success($status);
        } catch (Exception $e) {
            wp_send_json_error('Failed to get order sync status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Fetch current subscription status from backend
     * 
     * @param string $site_identifier
     * @param string $api_token
     */
    private function fetch_current_subscription_status($site_identifier, $api_token) {
        try {
            $backend_url = SHOPMETRICS_API_URL . '/v1/site-info';
            
            $headers = array(
                'Content-Type'                  => 'application/json',
                'X-FinanciarMe-Site-Identifier' => $site_identifier,
                'X-FinanciarMe-Token'           => $api_token,
            );
            
            $args = array(
                'method'  => 'GET',
                'headers' => $headers,
                'timeout' => 10,
            );

            \ShopMetrics_Logger::get_instance()->info("ShopMetrics Admin: Fetching current subscription status from backend");
            $response = wp_remote_get($backend_url, $args);

            if (is_wp_error($response)) {
                \ShopMetrics_Logger::get_instance()->error("ShopMetrics Admin: Error fetching subscription status: " . $response->get_error_message());
                return; // Keep existing status if API call fails
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($response_code === 200 && isset($data['subscription_status'])) {
                // Update WordPress options with fresh data from backend
                $current_status = $data['subscription_status'];
                $trial_ends_at = $data['trial_ends_at'] ?? null;
                $cancel_at = $data['cancel_at'] ?? null;
                
                // Update options
                update_option('shopmetrics_subscription_status', $current_status);
                if ($trial_ends_at) {
                    update_option('shopmetrics_trial_ends_at', $trial_ends_at);
                }
                if ($cancel_at) {
                    update_option('shopmetrics_cancel_at', $cancel_at);
                } else {
                    delete_option('shopmetrics_cancel_at');
                }

                \ShopMetrics_Logger::get_instance()->info("ShopMetrics Admin: Updated subscription status to: {$current_status}");
            } else {
                \ShopMetrics_Logger::get_instance()->warn("ShopMetrics Admin: Invalid response from site-info endpoint. Code: {$response_code}, Body: {$body}");
            }
            
        } catch (Exception $e) {
            \ShopMetrics_Logger::get_instance()->error("ShopMetrics Admin: Exception fetching subscription status: " . $e->getMessage());
        }
    }

    /**
     * Display Test Analytics page for debugging
     */
    public function display_test_analytics_page() {
        $nonce = wp_create_nonce('shopmetrics_nonce');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Error Tracking Test', 'shopmetrics'); ?></h1>
            <div class="card" style="max-width: 800px;">
                <h2>PHP Error Testing</h2>
                <p>Test PHP error tracking functionality. Errors will be logged for debugging.</p>
                
                <div style="margin: 20px 0;">
                    <button type="button" id="test-single-error" class="button button-primary">
                        Test Single PHP Error
                    </button>
                    <button type="button" id="test-error-scenarios" class="button button-primary" style="margin-left: 10px;">
                        Test Multiple Error Scenarios
                    </button>
                </div>
                
                <div id="test-results" style="margin-top: 20px; padding: 10px; background: #f1f1f1; border-radius: 4px; display: none;">
                    <h3>Test Results:</h3>
                    <div id="results-content"></div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            const nonce = '<?php echo esc_js($nonce); ?>';
            const ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
            
            function showResults(message, isSuccess = true) {
                $('#test-results').show();
                const color = isSuccess ? '#4CAF50' : '#f44336';
                $('#results-content').append(
                    '<div style="color: ' + color + '; margin: 5px 0;">' + 
                    '[' + new Date().toLocaleTimeString() + '] ' + message + 
                    '</div>'
                );
            }
            
            $('#test-single-error').click(function() {
                showResults('Sending test PHP error...', true);
                
                $.post(ajaxUrl, {
                    action: 'shopmetrics_test_php_error',
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        showResults('✅ Test PHP error sent successfully!', true);
                    } else {
                        showResults('❌ Test failed: ' + response.data.message, false);
                    }
                }).fail(function() {
                    showResults('❌ AJAX request failed', false);
                });
            });
            
            $('#test-error-scenarios').click(function() {
                showResults('Testing multiple PHP error scenarios...', true);
                
                $.post(ajaxUrl, {
                    action: 'shopmetrics_test_error_scenarios',
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        showResults('✅ Multiple error scenarios tested successfully!', true);
                        if (response.data.results) {
                            Object.keys(response.data.results).forEach(function(scenario) {
                                const result = response.data.results[scenario];
                                showResults('  - ' + scenario + ': ' + (result ? '✅ sent' : '❌ failed'), result);
                            });
                        }
                    } else {
                        showResults('❌ Test failed: ' + response.data.message, false);
                    }
                }).fail(function() {
                    showResults('❌ AJAX request failed', false);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for getting billing history
     */
    public function ajax_get_billing_history() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sm_settings_ajax_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'), 403);
            return;
        }
        
        $api_token = get_option('shopmetrics_analytics_api_token', '');
        $site_identifier = get_option('shopmetrics_analytics_site_identifier', '');
        
        if (empty($api_token) || empty($site_identifier)) {
            wp_send_json_error(array(
                'message' => 'Site not connected to API',
                'history' => []
            ));
            return;
        }
        
        try {
            // Ensure /v1/ is present in the endpoint
            $api_url_with_v1 = rtrim(SHOPMETRICS_API_URL, '/');
            if (!preg_match('#/v[0-9]+$#', $api_url_with_v1)) {
                $api_url_with_v1 .= '/v1';
            }
            $backend_url = $api_url_with_v1 . '/subscription/billing-history';
            
            $headers = array(
                'Content-Type'                  => 'application/json',
                'X-FinanciarMe-Site-Identifier' => $site_identifier,
                'X-FinanciarMe-Token'           => $api_token,
            );
            
            $args = array(
                'method'  => 'GET',
                'headers' => $headers,
                'timeout' => 15,
            );

            $response = wp_remote_get($backend_url, $args);

            if (is_wp_error($response)) {
                wp_send_json_error(array(
                    'message' => 'Failed to fetch billing history: ' . $response->get_error_message(),
                    'history' => []
                ));
                return;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($response_code === 200) {
                $data = json_decode($body, true);
                if (isset($data['billing_history'])) {
                    wp_send_json_success(array(
                        'history' => $data['billing_history']
                    ));
                } else {
                    wp_send_json_success(array(
                        'history' => []
                    ));
                }
            } else {
                wp_send_json_error(array(
                    'message' => 'Server returned error: ' . $response_code,
                    'history' => []
                ));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Error: ' . $e->getMessage(),
                'history' => []
            ));
        }
    }

    /**
     * Render the checkbox field for enabling debug logging.
     */
    public function render_enable_debug_logging_field() {
        $settings = get_option('shopmetrics_settings', []);
        ?>
        <label for="shopmetrics_enable_debug_logging" style="font-weight: 600; display: flex; align-items: center; gap: 8px;">
            <input type="hidden" name="shopmetrics_settings[enable_debug_logging]" value="0" />
            <input
                type="checkbox"
                id="shopmetrics_enable_debug_logging"
                name="shopmetrics_settings[enable_debug_logging]"
                value="1"
                <?php checked(!empty($settings['enable_debug_logging'])); ?>
            />
            <?php esc_html_e('Enable Debug Logging', 'shopmetrics'); ?>
        </label>
        <p class="description" style="margin-left: 2px;">
            <?php esc_html_e( 'Enable verbose debug logging for troubleshooting. This may log sensitive data. Disable in production.', 'shopmetrics' ); ?>
        </p>
        <?php
    }

    /**
     * Add sanitizer:
     */
    public function sanitize_settings_array($input) {
        $current = get_option('shopmetrics_settings', []);
        $output = array_merge($current, (array)$input);
        
        // Sanitize specific field types
        $checkboxes = [
            'enable_debug_logging',
            'enable_visit_tracking',
            'enable_low_stock_notifications',
            'enable_cart_recovery_emails',
            'cart_recovery_include_coupon',
        ];
        
        // Handle checkboxes: only process checkboxes that are actually submitted in the form
        foreach ($checkboxes as $cb) {
            if (array_key_exists($cb, $input)) {
                // Checkbox field is part of this form submission
                $output[$cb] = !empty($input[$cb]) ? 1 : 0;
            }
            // If checkbox is not in input at all, preserve existing value (don't modify $output)
        }
        
        // Sanitize text fields (only process fields that are in the input)
        $text_fields = [
            'cart_recovery_email_sender_name',
            'cart_recovery_email_sender_email', 
            'cart_recovery_email_subject',
            'cart_recovery_button_text',
            'cart_recovery_coupon_code',
            'cogs_meta_key',
            'low_stock_notification_recipients'
        ];
        
        foreach ($text_fields as $field) {
            if (array_key_exists($field, $input)) {
                if ($field === 'cart_recovery_email_sender_email') {
                    $output[$field] = sanitize_email($input[$field]);
                } else {
                    $output[$field] = sanitize_text_field($input[$field]);
                }
            }
        }
        
        // Sanitize numeric fields (only process fields that are in the input)
        $numeric_fields = [
            'cart_abandonment_threshold',
            'cart_recovery_email_delay',
            'cart_recovery_link_expiry',
            'cogs_default_percentage'
        ];
        
        foreach ($numeric_fields as $field) {
            if (array_key_exists($field, $input)) {
                $output[$field] = floatval($input[$field]);
            }
        }
        
        // Sanitize HTML content (only if present in input)
        if (array_key_exists('cart_recovery_email_content', $input)) {
            $output['cart_recovery_email_content'] = wp_kses_post($input['cart_recovery_email_content']);
        }
        
        return $output;
    }

    /**
     * Add migration logic (e.g. in admin_init):
     */
    public function maybe_migrate_old_settings() {
        $settings = get_option('shopmetrics_settings', false);
        if ($settings === false) {
            $settings = array();
            $settings['enable_visit_tracking'] = get_option('shopmetrics_analytics_enable_visit_tracking', true);
            $settings['cogs_meta_key'] = get_option('shopmetrics_analytics_cogs_meta_key', '');
            $settings['selected_order_blocks'] = get_option('shopmetrics_selected_order_blocks', 1);
            $settings['cogs_default_percentage'] = get_option('shopmetrics_analytics_cogs_default_percentage', null);
            $settings['enable_low_stock_notifications'] = get_option('shopmetrics_analytics_enable_low_stock_notifications', false);
            $settings['low_stock_notification_recipients'] = get_option('shopmetrics_analytics_low_stock_notification_recipients', '');
            $settings['enable_debug_logging'] = get_option('shopmetrics_enable_debug_logging', false);
            $settings['analytics_consent'] = get_option('shopmetrics_analytics_consent', true);
            update_option('shopmetrics_settings', $settings);
            // Удаляем старые опции
            delete_option('shopmetrics_analytics_enable_visit_tracking');
            delete_option('shopmetrics_analytics_cogs_meta_key');
            delete_option('shopmetrics_selected_order_blocks');
            delete_option('shopmetrics_analytics_cogs_default_percentage');
            delete_option('shopmetrics_analytics_enable_low_stock_notifications');
            delete_option('shopmetrics_analytics_low_stock_notification_recipients');
            delete_option('shopmetrics_enable_debug_logging');
            delete_option('shopmetrics_analytics_consent');
            delete_option('shopmetrics_analytics_settings');
            delete_option('shopmetrics_analytics_settings_group');
        }
    }

    /**
     * AJAX handler for saving multiple settings to shopmetrics_settings (array)
     *
     * @since 2.0.0
     */
    public function handle_ajax_save_settings() {
        // Проверка nonce
        if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_ajax_nonce'])), 'sm_settings_ajax_nonce')) {
            wp_send_json_error(['message' => 'Invalid security token. Please refresh the page and try again.'], 403);
        }

        // Проверка прав
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'You do not have permission to change settings.'], 403);
        }

        // Получаем массив настроек
        $settings = isset($_POST['shopmetrics_settings']) && is_array($_POST['shopmetrics_settings'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['shopmetrics_settings']))
            : [];

        if (empty($settings)) {
            wp_send_json_error(['message' => 'No settings provided.'], 400);
        }

        // Получаем текущие настройки и патчим их
        $current = get_option('shopmetrics_settings', []);
        $merged = array_merge($current, $settings);

        // Сохраняем
        $result = update_option('shopmetrics_settings', $merged);

        if ($result) {
            wp_send_json_success(['message' => 'Settings saved successfully.']);
        } else {
            wp_send_json_error(['message' => 'Failed to save settings. The value might be unchanged.']);
        }
    }
} 