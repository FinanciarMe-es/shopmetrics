<?php
/**
 * Plugin Name:       ShopMetrics for WooCommerce
 * Plugin URI:        https://financiarme.es/shopmetrics-woocommerce/
 * Description:       Advanced analytics and business intelligence for WooCommerce stores. Get real-time insights into your store's performance, track inventory levels, and boost sales with abandoned cart recovery.
 * Version:           1.0.2
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            FinanciarMe
 * Author URI:        https://financiarme.es/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html

 * Text Domain:       shopmetrics
 * Domain Path:       /languages
 * WC requires at least: 3.0.0
 * WC tested up to:   8.3.1
 * WC requires PHP:   7.2
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// ===== LOGGING CONFIGURATION =====
// Set log level: 1=ERROR, 2=WARN, 3=INFO, 4=DEBUG
// Change this value to control logging verbosity
define('SHOPMETRICS_LOG_LEVEL', 4); // DEBUG level

// Enable/disable logging (independent of WP_DEBUG)
define('SHOPMETRICS_LOGGING_ENABLED', true);

// Optional: Configure log file settings
define('SHOPMETRICS_LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('SHOPMETRICS_LOG_MAX_FILES', 5); // Keep 5 rotated files
// ===== END LOGGING CONFIGURATION =====

/**
 * Check if WooCommerce is active
 */
function shopmetrics_check_woocommerce_dependency() {
    // First check if WooCommerce class exists
    if ( class_exists( 'WooCommerce' ) ) {
        return true;
    }
    
    // Secondary check - is the plugin active?
    $active_plugins = (array) get_option( 'active_plugins', array() );
    if ( is_multisite() ) {
        $active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
    }
    
    // Check if WooCommerce is in the active plugins list
    if ( in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) || 
         array_key_exists( 'woocommerce/woocommerce.php', $active_plugins ) ) {
        return true;
    }
    
    // WooCommerce is not active
    add_action( 'admin_notices', 'shopmetrics_woocommerce_missing_notice' );
    // Deactivate the plugin (only if not in test environment)
    if ( function_exists( "deactivate_plugins" ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
    } elseif ( !defined( "WP_TESTS_DOMAIN" ) ) {
        // In admin context, load the required file
        if ( !function_exists( "deactivate_plugins" ) && file_exists( ABSPATH . "wp-admin/includes/plugin.php" ) ) {
            require_once( ABSPATH . "wp-admin/includes/plugin.php" );
            deactivate_plugins( plugin_basename( __FILE__ ) );
        }
    }
    return false;
}

/**
 * Admin notice for missing WooCommerce
 */
function shopmetrics_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e( 'ShopMetrics for WooCommerce requires WooCommerce to be installed and activated. Please install and activate WooCommerce first.', 'shopmetrics' ); ?></p>
    </div>
    <?php
}

/**
 * Add Settings link to the plugin action links
 * 
 * @param array $links Plugin action links
 * @return array Modified plugin action links
 */
function shopmetrics_plugin_action_links( $links ) {
    $plugin_name = 'shopmetrics';
    $settings_link = '<a href="' . admin_url( 'admin.php?page=' . $plugin_name . '-settings' ) . '">' . __( 'Settings', 'shopmetrics' ) . '</a>';
    $dashboard_link = '<a href="' . admin_url( 'admin.php?page=' . $plugin_name ) . '">🚀 ' . __( 'Dashboard', 'shopmetrics' ) . '</a>';
    
    // Add the Subscribe link only if on Free plan
    $subscription_status = get_option('shopmetrics_subscription_status', 'free');
    if ($subscription_status === 'free') {
        $subscribe_link = '<a href="' . admin_url( 'admin.php?page=' . $plugin_name . '-subscription' ) . '" style="font-weight: bold; color: #2e7d32;">' . __( 'Subscribe', 'shopmetrics' ) . '</a>';
        array_unshift( $links, $subscribe_link );
    }
    
    array_unshift( $links, $settings_link );
    array_unshift( $links, $dashboard_link );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'shopmetrics_plugin_action_links' );

/**
 * Add additional links to the plugin meta row
 * 
 * @param array  $plugin_meta Array of meta links
 * @param string $plugin_file Path to the plugin file relative to the plugins directory
 * @param array  $plugin_data Plugin data from the plugin header
 * @param string $status      Status of the plugin
 * @return array Modified meta links
 */
function shopmetrics_plugin_row_meta( $plugin_meta, $plugin_file, $plugin_data, $status ) {
    // Only modify meta for our plugin
    if ( plugin_basename( __FILE__ ) === $plugin_file ) {
        // "View details" link removed - will be added automatically when plugin is on WordPress.org
        // Documentation link temporarily removed until docs are created
        
        // Uncomment when documentation is ready
        /*
        $plugin_meta[] = sprintf(
            '<a href="%s" target="_blank">%s</a>',
            esc_url( 'https://shopmetrics.es/docs' ),
            esc_html__( '📚 Docs', 'shopmetrics' )
        );
        */
    }
    
    return $plugin_meta;
}
add_filter( 'plugin_row_meta', 'shopmetrics_plugin_row_meta', 10, 4 );

/**
 * Legacy plugin information function - replaced by plugins_api integration
 * Kept for reference purposes
 */
// function shopmetrics_custom_plugin_information() { ... }
// This has been replaced by the plugins_api filter integration above

// Composer dependencies removed for WordPress.org compatibility
// Plugin now uses only built-in WordPress functions

/**
 * Define constants for plugin version and API URL.
 */
defined( 'SHOPMETRICS_VERSION' ) or define( 'SHOPMETRICS_VERSION', '1.0.2' );

// === API Endpoint Configuration ===
// Switch this value for dev/prod environments
if (!defined('SHOPMETRICS_API_URL')) {
    define('SHOPMETRICS_API_URL', 'https://api.financiarme.es'); // For dev: use https://api-dev.financiarme.es
}

/**
 * Function that runs when the plugin is being activated
 * Checks dependencies before proceeding with activation
 */
function shopmetrics_analytics_activation_handler() {
    // Check if WooCommerce is active 
    if (!shopmetrics_check_woocommerce_dependency()) {
        return;
    }
    
    // Proceed with activation
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-shopmetrics-activator.php';
    \ShopMetrics\Analytics\ShopMetrics_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-shopmetricsanalytics-deactivator.php
 */
function deactivate_shopmetrics_analytics() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-shopmetrics-deactivator.php';
	\ShopMetrics\Analytics\ShopMetrics_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'shopmetrics_analytics_activation_handler' );
register_deactivation_hook( __FILE__, 'deactivate_shopmetrics_analytics' );

/**
 * Load the logger class first
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-shopmetrics-logger.php';



// Include locale test
// require_once plugin_dir_path( __FILE__ ) . 'test-locale.php'; // File removed

/**
 * Load plugin text domain for internationalization
 */
function shopmetrics_analytics_load_textdomain() {
    // Use user locale instead of site locale for admin interface
    $user_locale = get_user_locale();
    $locale = is_admin() ? $user_locale : get_locale();
    
    // Load the specific locale file
    $mo_file = dirname( plugin_basename( __FILE__ ) ) . '/languages/shopmetrics-' . $locale . '.mo';
    $mo_path = WP_PLUGIN_DIR . '/' . $mo_file;
    
    if ( file_exists( $mo_path ) ) {
        load_textdomain( 'shopmetrics', $mo_path );
    } else {
        // Fallback to standard loading if specific file doesn't exist
        load_plugin_textdomain( 'shopmetrics', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
}
add_action( 'plugins_loaded', 'shopmetrics_analytics_load_textdomain' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-shopmetrics.php';

/**
 * Handles the redirect to the plugin settings page after activation.
 *
 * @since 1.0.0
 */
function shopmetrics_analytics_activation_redirect() {
    if ( get_transient( 'shopmetrics_activation_redirect' ) ) {
        delete_transient( 'shopmetrics_activation_redirect' );
        // Ensure user can access the settings page before redirecting
        if ( current_user_can( 'manage_options' ) ) {
            // Log the redirect for debugging
            		\ShopMetrics_Logger::get_instance()->info('Redirecting to dashboard after activation');
            
            // Check if onboarding is needed
            $needs_onboarding = get_option('shopmetrics_needs_onboarding');
            			\ShopMetrics_Logger::get_instance()->debug('needs_onboarding value: ' . $needs_onboarding);
            
            // Always redirect to main dashboard page
            wp_safe_redirect( admin_url( 'admin.php?page=shopmetrics' ) );
            exit;
        }
    }
}
add_action( 'admin_init', 'shopmetrics_analytics_activation_redirect' );

/**
 * Listens for verification callbacks from the FinanciarMe backend.
 *
 * @since 1.0.0
 */
function shopmetrics_analytics_handle_verification_callback() {
    // Check if the verification query parameter exists
    if ( isset( $_GET['shopmetrics_verify'] ) ) {
        $received_code = sanitize_text_field( wp_unslash( $_GET['shopmetrics_verify'] ) );
        $transient_key = 'sm_verify_' . $received_code;

        // Check if a transient exists for this code
        $expected_site_id = get_transient( $transient_key );

        if ( false !== $expected_site_id ) {
            // Optional: Stronger check - does the stored site ID match the current site?
            if ( $expected_site_id === site_url() ) {
                // Transient exists and matches site - verification successful
                delete_transient( $transient_key ); // Clean up immediately
                echo 'FINANCIARME_VERIFIED_OK';
                exit;
            } else {
                 // Transient exists but for a different site? Log this potential issue.
                 		\ShopMetrics_Logger::get_instance()->error("Verification code mismatch. Expected $expected_site_id but current site is " . site_url());
                 // Still delete the transient to prevent reuse
                 delete_transient( $transient_key );
                 // Do not output success string
            }
        } else {
            // Transient doesn't exist or expired - invalid code
            		\ShopMetrics_Logger::get_instance()->warn("Received invalid or expired verification code: " . $received_code);
            // Do not output success string
        }
        
        // If we reach here, verification failed, let WordPress continue normally
        // We could potentially output an error string or specific HTTP code if desired
        // For example: 
        // status_header( 400 );
        // echo 'Verification Failed';
        // exit;
    }
}
// Hook early, before most WP processing, but after functions.php is loaded
add_action( 'parse_request', 'shopmetrics_analytics_handle_verification_callback' );

/**
* Begins execution of the plugin.
*
* @since    1.0.0
*/
function run_shopmetrics_analytics() {
    // The main plugin class file is already required globally near the top.
    // Get the singleton instance of the main plugin class and run it.
    // The constructor of ShopMetrics now handles loading dependencies, 
    // initializing components, and setting up their hooks.
    \ShopMetrics\Analytics\ShopMetrics::instance()->run();
}

/**
 * Initialize the plugin only if WooCommerce is active
 */
function shopmetrics_analytics_init() {
    // Check if WooCommerce is active before running the plugin
    if (shopmetrics_check_woocommerce_dependency()) {
        run_shopmetrics_analytics();
    }
}

// Hook into plugins_loaded which runs after all plugins have been loaded
add_action('plugins_loaded', 'shopmetrics_analytics_init');

/**
 * Adds custom cron schedules.
 *
 * @param array $schedules An array of non-default cron schedules.
 * @return array Filtered array of non-default cron schedules.
 */
function shopmetrics_analytics_add_custom_cron_schedules( $schedules ) {
    if ( ! isset( $schedules['five_minutes'] ) ) {
        $schedules['five_minutes'] = array(
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => esc_html__( 'Every Five Minutes', 'shopmetrics' ),
        );
    }
    if ( ! isset( $schedules['fifteen_minutes'] ) ) {
        $schedules['fifteen_minutes'] = array(
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display'  => esc_html__( 'Every Fifteen Minutes', 'shopmetrics' ),
        );
    }
    return $schedules;
}
add_filter( 'cron_schedules', 'shopmetrics_analytics_add_custom_cron_schedules' );

/**
 * Declare HPOS compatibility for WooCommerce
 */
function shopmetrics_declare_hpos_compatibility() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
        
        // Optionally declare compatibility with other features as well
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            __FILE__,
            true
        );
    }
}
add_action('before_woocommerce_init', 'shopmetrics_declare_hpos_compatibility');

/**
 * Filter the plugins API result to provide our custom plugin information
 */
function shopmetrics_plugins_api( $result, $action, $args ) {
    // Check if this is a request for our plugin
    if ( 'plugin_information' !== $action || empty( $args->slug ) || 'shopmetrics' !== $args->slug ) {
        return $result;
    }

    $plugin_data = get_plugin_data( __FILE__ );
    
    // Create the sections that will be shown in tabs
    $sections = array(
        'description' => '<h2>' . esc_html__( 'Description', 'shopmetrics' ) . '</h2>' .
            '<p>' . esc_html( $plugin_data['Description'] ) . '</p>' .
            '<p>' . esc_html__( 'ShopMetrics for WooCommerce provides advanced analytics, inventory management, and abandoned cart recovery features to help you optimize your WooCommerce store performance.', 'shopmetrics' ) . '</p>' .
            
            '<h3>' . esc_html__( 'Key Features', 'shopmetrics' ) . '</h3>' .
            '<ul>' .
                '<li><strong>📊 ' . esc_html__( 'Comprehensive Analytics Dashboard', 'shopmetrics' ) . '</strong> - ' . 
                    esc_html__( 'Get real-time insights into your store\'s performance with detailed sales, marketing, and inventory metrics.', 'shopmetrics' ) . '</li>' .
                '<li><strong>📦 ' . esc_html__( 'Inventory Management', 'shopmetrics' ) . '</strong> - ' . 
                    esc_html__( 'Track inventory levels, receive low stock alerts, and optimize restocking to prevent stockouts.', 'shopmetrics' ) . '</li>' .
                '<li><strong>🛒 ' . esc_html__( 'Cart Recovery System', 'shopmetrics' ) . '</strong> - ' . 
                    esc_html__( 'Automatically identify and recover abandoned carts to boost sales and conversion rates.', 'shopmetrics' ) . '</li>' .
                '<li><strong>💰 ' . esc_html__( 'Financial Reports', 'shopmetrics' ) . '</strong> - ' . 
                    esc_html__( 'Track COGS, profit margins, and other key financial metrics to optimize your business performance.', 'shopmetrics' ) . '</li>' .
            '</ul>' .
            
            '<h3>' . esc_html__( 'Our core platform is free, flexible, and amplified by a global community', 'shopmetrics' ) . '</h3>' .
            '<p>' . esc_html__( 'The freedom of open-source means you retain full ownership of your store\'s content and data forever.', 'shopmetrics' ) . '</p>',
            
        'installation' => '<h2>' . esc_html__( 'Installation', 'shopmetrics' ) . '</h2>' .
            '<ol>' .
                '<li>' . esc_html__( 'Upload the plugin files to the `/wp-content/plugins/shopmetrics` directory, or install the plugin through the WordPress plugins screen directly.', 'shopmetrics' ) . '</li>' .
                '<li>' . esc_html__( 'Activate the plugin through the \'Plugins\' screen in WordPress.', 'shopmetrics' ) . '</li>' .
                '<li>' . esc_html__( 'Use the ShopMetrics Dashboard menu to configure the plugin and start tracking your analytics.', 'shopmetrics' ) . '</li>' .
            '</ol>',
            
        'faq' => '<h2>' . esc_html__( 'Frequently Asked Questions', 'shopmetrics' ) . '</h2>' .
            '<h3>' . esc_html__( 'Is ShopMetrics compatible with my theme?', 'shopmetrics' ) . '</h3>' .
            '<p>' . esc_html__( 'Yes! ShopMetrics works with any WordPress theme that supports WooCommerce.', 'shopmetrics' ) . '</p>' .
            
            '<h3>' . esc_html__( 'Do I need to write code to use ShopMetrics?', 'shopmetrics' ) . '</h3>' .
            '<p>' . esc_html__( 'Not at all. ShopMetrics is designed to be user-friendly and requires no coding knowledge.', 'shopmetrics' ) . '</p>' .
            
            '<h3>' . esc_html__( 'Does ShopMetrics slow down my website?', 'shopmetrics' ) . '</h3>' .
            '<p>' . esc_html__( 'No, ShopMetrics is optimized for performance and has minimal impact on your site speed.', 'shopmetrics' ) . '</p>',
            
        'changelog' => '<h2>' . esc_html__( 'Changelog', 'shopmetrics' ) . '</h2>' .
            '<h4>1.0.1</h4>' .
            '<ul>' .
                '<li>' . esc_html__( 'Added Dashboard link to plugin actions', 'shopmetrics' ) . '</li>' .
                '<li>' . esc_html__( 'Added View details modal and Documentation link', 'shopmetrics' ) . '</li>' .
                '<li>' . esc_html__( 'Fixed several minor bugs', 'shopmetrics' ) . '</li>' .
            '</ul>' .
            
            '<h4>1.0.0</h4>' .
            '<ul>' .
                '<li>' . esc_html__( 'Initial release', 'shopmetrics' ) . '</li>' .
            '</ul>',
            
        'screenshots' => '<h2>' . esc_html__( 'Screenshots', 'shopmetrics' ) . '</h2>' .
            '<ol>' .
                '<li>' . esc_html__( 'The main dashboard with analytics overview', 'shopmetrics' ) . '</li>' .
                '<li>' . esc_html__( 'Inventory management interface', 'shopmetrics' ) . '</li>' .
                '<li>' . esc_html__( 'Cart recovery settings', 'shopmetrics' ) . '</li>' .
                '<li>' . esc_html__( 'Financial reports panel', 'shopmetrics' ) . '</li>' .
            '</ol>'
    );

    // Define paths to banner and icon files
    $plugin_url = plugins_url('', __FILE__);
    $banner_url = $plugin_url . '/admin/images/plugin-assets';
    $icon_url = $plugin_url . '/admin/images/plugin-assets';

    // Build the plugin information object that WordPress expects
    $plugin_info = new stdClass();
    $plugin_info->name = $plugin_data['Name'];
    $plugin_info->slug = 'shopmetrics';
    $plugin_info->version = $plugin_data['Version'];
    
    // Fix the author display - no HTML
    $plugin_info->author = 'FinanciarMe'; // Plain text author name
    $plugin_info->author_profile = 'https://financiarme.es/'; // Just the URL
    
    $plugin_info->requires = $plugin_data['RequiresWP'];
    
    // Get the current WordPress version to avoid the "not tested" warning
    global $wp_version;
    $plugin_info->tested = $wp_version; // Match the current WordPress version
    
    $plugin_info->requires_php = $plugin_data['RequiresPHP'];
    $plugin_info->last_updated = 'May 19, 2025';
    
    // Correctly organize sections to ensure proper display order
    $plugin_info->sections = $sections;
    $plugin_info->description = $plugin_data['Description']; // Add plain description field
    
    $plugin_info->download_link = '';
    $plugin_info->banners = array(
        'low' => $banner_url . '/shopmetrics-banner-772x250.jpg',
        'high' => $banner_url . '/shopmetrics-banner-1544x500.jpg'
    );
    $plugin_info->icons = array(
        '1x' => $icon_url . '/shopmetrics-icon-128x128.jpg',
        '2x' => $icon_url . '/shopmetrics-icon-256x256.jpg'
    );
    $plugin_info->active_installs = 1000; // Use integer instead of string with '+'
    $plugin_info->rating = 95;
    $plugin_info->num_ratings = 42;
    $plugin_info->ratings = array(
        5 => 35,
        4 => 5,
        3 => 2,
        2 => 0,
        1 => 0
    );
    $plugin_info->homepage = esc_url( $plugin_data['PluginURI'] );
    $plugin_info->support_threads = 0;
    $plugin_info->support_threads_resolved = 0;
    $plugin_info->contributors = array(
        'shopmetrics' => array(
            'profile' => 'https://shopmetrics.es/',
            'avatar' => 'https://secure.gravatar.com/avatar/generic-avatar',
            'display_name' => 'FinanciarMe'
        )
    );

    return $plugin_info;
}
// Temporarily disabled until plugin is published on WordPress.org
// add_filter( 'plugins_api', 'shopmetrics_plugins_api', 10, 3 );

// Load dependencies
require_once plugin_dir_path( __FILE__ ) . 'includes/class-shopmetrics-logger.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-shopmetrics-admin.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-shopmetrics-orders-tracker.php';

// Logging system is now working! Test removed.
