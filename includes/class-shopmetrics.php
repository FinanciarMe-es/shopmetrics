<?php

namespace ShopMetrics\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also, it is responsible for initializing all the components of the plugin
 * and providing a single point of access to them.
 *
 * @since      1.0.0
 * @package    ShopMetrics
 * @subpackage ShopMetrics/includes
 * @author     FinanciarMe <info@financiarme.es>
 */
class ShopMetrics {

	/**
	 * The single instance of the class.
	 *
	 * @since  1.2.0
	 * @var    ShopMetrics
	 */
	private static $_instance = null;



    /**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	public $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @var      string    $version    The current version of the plugin.
	 */
	public $version;

	/**
	 * The admin-specific functionality of the plugin.
	 *
	 * @since 1.2.0
	 * @var   \ShopMetrics\Analytics\ShopMetrics_Admin
	 */
    public $admin;

	/**
	 * The cart tracking functionality of the plugin.
	 *
	 * @since 1.2.0
	 * @var   \ShopMetrics\Analytics\ShopMetrics_Cart_Tracker
	 */
    public $cart_tracker;

	/**
	 * The cart recovery functionality of the plugin.
	 *
	 * @since 1.2.0
	 * @var   \ShopMetrics\Analytics\ShopMetrics_Cart_Recovery
	 */
    public $cart_recovery;

	/**
	 * The orders tracking functionality of the plugin.
	 *
	 * @since 1.2.0
	 * @var   \ShopMetrics\Analytics\ShopMetrics_Orders_Tracker
	 */
	public $orders_tracker;

	/**
	 * The data collection functionality of the plugin.
	 *
	 * @since 1.2.0
	 * @var   \ShopMetrics\Analytics\ShopMetrics_Data_Collector
	 */
	public $data_collector;

	/**
	 * The REST API functionality of the plugin.
	 *
	 * @since 1.2.0
	 * @var   \ShopMetrics\Analytics\ShopMetrics_Rest_Api
	 */
	public $rest_api;

    /**
	 * Main ShopMetrics Instance.
	 *
	 * Ensures only one instance of ShopMetrics is loaded or can be loaded.
	 *
	 * @since  1.2.0
	 * @static
	 * @return ShopMetrics - Main instance.
     */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
        }
		return self::$_instance;
    }
    
    /**
	 * Cloning is forbidden.
	 * @since 1.2.0
	 */
	public function __clone() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'shopmetrics' ), '1.2.0' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 * @since 1.2.0
	 */
	public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'shopmetrics' ), '1.2.0' );
	}

	/**
	 * Constructor for the main plugin class.
	 *
	 * @since 1.0.0
     */
    private function __construct() {
		$this->version = defined( 'SHOPMETRICS_VERSION' ) ? SHOPMETRICS_VERSION : '1.0.2';
		$this->plugin_name = 'shopmetrics';
        
        $this->load_dependencies();
		$this->init_components();
		$this->init_hooks();
	}

    /**
     * Load the required dependencies for this plugin.
	 *
	 * @since 1.0.0
     */
    private function load_dependencies() {
		// These files are already included in the main plugin file, but we'll include them here for safety
		$includes_path = plugin_dir_path( dirname( __FILE__ ) ) . 'includes/';
		
		if ( file_exists( $includes_path . 'class-shopmetrics-api-client.php' ) ) {
			require_once $includes_path . 'class-shopmetrics-api-client.php';
		}
		if ( file_exists( $includes_path . 'class-shopmetrics-cart-tracker.php' ) ) {
			require_once $includes_path . 'class-shopmetrics-cart-tracker.php';
		}
		if ( file_exists( $includes_path . 'class-shopmetrics-cart-recovery.php' ) ) {
			require_once $includes_path . 'class-shopmetrics-cart-recovery.php';
		}
		if ( file_exists( $includes_path . 'class-shopmetrics-analytics.php' ) ) {
			require_once $includes_path . 'class-shopmetrics-analytics.php';
		}
		if ( file_exists( $includes_path . 'class-shopmetrics-orders-tracker.php' ) ) {
			require_once $includes_path . 'class-shopmetrics-orders-tracker.php';
		}
	}

	/**
	 * Initialize all the plugin components.
	 *
	 * @since 1.2.0
	 */
	private function init_components() {
		// Only initialize the components we actually need and that exist
		if ( class_exists( '\ShopMetrics\Analytics\ShopMetrics_Admin' ) ) {
			$this->admin = new \ShopMetrics\Analytics\ShopMetrics_Admin( $this );
		}
		
		if ( class_exists( '\ShopMetrics\Analytics\ShopMetrics_Cart_Tracker' ) ) {
			$this->cart_tracker = new \ShopMetrics\Analytics\ShopMetrics_Cart_Tracker( $this );
		}
		
		if ( class_exists( '\ShopMetrics\Analytics\ShopMetrics_Cart_Recovery' ) && $this->cart_tracker ) {
			$this->cart_recovery = new \ShopMetrics\Analytics\ShopMetrics_Cart_Recovery( $this, $this->cart_tracker );
		}
		
		if ( class_exists( '\ShopMetrics\Analytics\ShopMetrics_Orders_Tracker' ) ) {
			$this->orders_tracker = new \ShopMetrics\Analytics\ShopMetrics_Orders_Tracker( $this );
		}
		
		if ( class_exists( '\ShopMetrics\Analytics\ShopMetrics_Data_Collector' ) ) {
			$this->data_collector = new \ShopMetrics\Analytics\ShopMetrics_Data_Collector( $this );
		}
		
		if ( class_exists( '\ShopMetrics\Analytics\ShopMetrics_Rest_Api' ) ) {
			$this->rest_api = new \ShopMetrics\Analytics\ShopMetrics_Rest_Api();
		}
    }

    /**
	 * Register all of the hooks related to the plugin.
	 *
	 * @since 1.2.0
     */
	private function init_hooks() {
		// I18N hooks
		add_action( 'plugins_loaded', array( $this, 'set_locale' ) );

		// Admin hooks
		if ( $this->admin ) {
        $this->admin->init_hooks();
		}

		// Cart tracker hooks
		if ( $this->cart_tracker ) {
			$this->cart_tracker->init_hooks();
        }

		// Cart recovery hooks
		if ( $this->cart_recovery ) {
			$this->cart_recovery->init_hooks();
		}
		
		// Orders tracker hooks
		if ( $this->orders_tracker ) {
        $this->orders_tracker->init_hooks();
        $this->orders_tracker->init_async_hooks();
		}

		// REST API hooks
		if ( $this->rest_api ) {
        $this->rest_api->init_hooks();
		}
		
		// HPOS compatibility
		add_action( 'before_woocommerce_init', array( $this, 'define_hpos_compatibility' ) );
		
		// Frontend scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_scripts' ) );
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * @since    1.0.0
	 */
	public function set_locale() {
		// Simplified locale loading - the main plugin file already handles this
		load_plugin_textdomain( 'shopmetrics', false, dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages' );
	}
	
	/**
	 * Declare HPOS compatibility.
	 *
	 * @since 1.0.2
	 */
	public function define_hpos_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
    }

    /**
	 * Run the plugin - hooks are already initialized in constructor.
	 *
	 * @since    1.0.0
     */
	public function run() {
		// Hooks are already registered in init_hooks(), so nothing to do here
	}

	/**
	 * The name of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
    }

    /**
     * Gets the Cart_Tracker instance.
     *
	 * @since    1.2.0
	 * @return   \ShopMetrics_Cart_Tracker|null    The cart tracker instance, or null if not initialized.
     */
    public function get_cart_tracker() {
        return $this->cart_tracker;
    }

    /**
	 * Gets the Cart_Recovery instance.
     *
	 * @since    1.2.0
	 * @return   \ShopMetrics_Cart_Recovery|null The cart recovery instance, or null if not initialized.
     */
	public function get_cart_recovery() {
		return $this->cart_recovery;
    }

    /**
     * Gets the Orders_Tracker instance.
     *
	 * @since    1.2.0
	 * @return   \ShopMetrics_Orders_Tracker|null The orders tracker instance, or null if not initialized.
     */
    public function get_orders_tracker() {
        return $this->orders_tracker;
    }

    /**
	 * Enqueue public scripts (for frontend).
     *
	 * @since    1.2.0
     */
    public function enqueue_public_scripts() {
        $settings = get_option('shopmetrics_settings', []);
        $api_token = get_option('shopmetrics_analytics_api_token', '');


		
		// Only enqueue if tracking is enabled and we have a token
		if (!empty($settings['enable_visit_tracking']) && !empty($api_token)) {
			$script_url = plugin_dir_url( dirname( __FILE__ ) ) . 'js/visit-tracker.js';

            wp_enqueue_script(
                'shopmetrics-visit-tracker', 
				$script_url,
                array(), 
                $this->version, 
                true 
            );

			// Determine page type
			$page_type = 'page';
			if (function_exists('is_home') && (is_home() || is_front_page())) {
				$page_type = 'home';
			} elseif (function_exists('is_shop') && (is_shop() || is_product_category() || is_product_tag())) {
				$page_type = 'shop';
			} elseif (function_exists('is_product') && is_product()) {
				$page_type = 'product';
			} elseif (function_exists('is_cart') && is_cart()) {
				$page_type = 'cart';
			} elseif (function_exists('is_checkout') && is_checkout()) {
				$page_type = 'checkout';
			} elseif (function_exists('is_account_page') && is_account_page()) {
				$page_type = 'account';
			} elseif (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) {
				$page_type = 'order_received';
			}

			
			// Localize script with data
            $localize_data = array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('sm_visit_tracking_nonce'),
                'pageType' => $page_type,
				'debugLogging' => !empty($settings['enable_debug_logging']),
				'orderId' => (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) ? get_query_var('order-received') : null,
			);

			
			wp_localize_script('shopmetrics-visit-tracker', 'fmVisitTrackerData', $localize_data);
		}
	}
} 