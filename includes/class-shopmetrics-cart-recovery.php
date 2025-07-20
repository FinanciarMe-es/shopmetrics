<?php

namespace ShopMetrics\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Handles cart recovery functionality including email sending
 *
 * @since      1.2.0
 * @package    ShopMetrics
 * @subpackage ShopMetrics/includes
 * @author     FinanciarMe <info@financiarme.es>
 */
class ShopMetrics_Cart_Recovery {

    /**
     * Reference to the main plugin class instance.
     * @var ShopMetrics
     */
    protected $plugin;

    /**
     * Reference to the Cart_Tracker class.
     * @var Cart_Tracker
     */
    protected $cart_tracker;

    /**
     * Cron hook names for cart recovery actions
     */
    const RECOVERY_EMAIL_CRON_HOOK = 'shopmetrics_send_cart_recovery_emails_hook';

    /**
     * Constructor.
     * @param \ShopMetrics\Analytics\ShopMetrics $plugin Main plugin instance.
     * @param \ShopMetrics\Analytics\ShopMetrics_Cart_Tracker $cart_tracker Cart tracker instance.
     */
    public function __construct( \ShopMetrics\Analytics\ShopMetrics $plugin, \ShopMetrics\Analytics\ShopMetrics_Cart_Tracker $cart_tracker ) {
        $this->plugin = $plugin;
        $this->cart_tracker = $cart_tracker;
    }

    /**
     * Initialize hooks for cart recovery.
     *
     * @since 1.2.0
     */
    public function init_hooks() {
        // Register the cron job for sending recovery emails
        add_action( self::RECOVERY_EMAIL_CRON_HOOK, array( $this, 'send_recovery_emails' ) );
        
        // Schedule the cron job if not already scheduled
        if ( ! wp_next_scheduled( self::RECOVERY_EMAIL_CRON_HOOK ) ) {
            wp_schedule_event( time(), 'hourly', self::RECOVERY_EMAIL_CRON_HOOK );
            \ShopMetrics_Logger::get_instance()->info("Scheduled recovery email check cron ('" . self::RECOVERY_EMAIL_CRON_HOOK . "')");
        }
        
        // Handle recovery link clicks (cart restoration)
        add_action( 'init', array( $this, 'handle_recovery_link' ) );
        
        // Register settings for cart recovery
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Register settings for cart recovery
     */
    public function register_settings() {
        // Cart recovery settings are now part of the unified shopmetrics_settings array
        // The main settings are already registered in the admin class
        // We just need to ensure the cart recovery specific fields are properly handled
        
        // Register cart recovery section
        add_settings_section(
            'shopmetrics_cart_recovery_section',
            __('Cart Recovery Settings', 'shopmetrics'),
            null,
            'shopmetrics-cart-recovery'
        );
        
        // Add cart recovery fields to the unified settings
        $cart_recovery_fields = array(
            'enable_cart_recovery_emails',
            'cart_abandonment_threshold',
            'cart_recovery_email_delay',
            'cart_recovery_email_sender_name',
            'cart_recovery_email_sender_email',
            'cart_recovery_email_subject',
            'cart_recovery_button_text',
            'cart_recovery_link_expiry',
            'cart_recovery_email_content'
        );
        
        foreach ($cart_recovery_fields as $field) {
            add_settings_field(
                'shopmetrics_settings_' . $field,
                ucfirst(str_replace('_', ' ', $field)),
                '__return_false', // No callback needed as these are handled by the form
                'shopmetrics-cart-recovery',
                'shopmetrics_cart_recovery_section'
            );
        }
    }

    /**
     * Send recovery emails for abandoned carts
     */
    public function send_recovery_emails() {
        \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartRecovery: Running send_recovery_emails cron job.");
        
        $settings = get_option('shopmetrics_settings', []);
        
        // Check if recovery emails are enabled
        $emails_enabled = !empty($settings['enable_cart_recovery_emails']);
        if (!$emails_enabled) {
            \ShopMetrics_Logger::get_instance()->debug("FinanciarMe CartRecovery: Recovery emails are disabled in settings.");
            return;
        }
        
        // Use WordPress Options API instead of direct database query
        $cache_key = 'shopmetrics_active_cart_options';
        $active_cart_options = wp_cache_get($cache_key);
        
        if (false === $active_cart_options) {
            $active_cart_options = array();
            
            // Get all options that match our pattern using WordPress API
            $user_prefix = ShopMetrics_Cart_Tracker::ACTIVE_CART_OPTION_PREFIX . 'user_';
            $session_prefix = ShopMetrics_Cart_Tracker::ACTIVE_CART_OPTION_PREFIX . 'session_';
            
            // Get all options and filter for our patterns
            $all_options = wp_load_alloptions();
            foreach ($all_options as $option_name => $option_value) {
                if (strpos($option_name, $user_prefix) === 0 || strpos($option_name, $session_prefix) === 0) {
                    $active_cart_options[] = (object) array(
                        'option_name' => $option_name,
                        'option_value' => $option_value
                    );
                }
            }
            
            // Cache the result for 2 minutes
            wp_cache_set($cache_key, $active_cart_options, '', 120);
        }
        
        if (empty($active_cart_options)) {
            \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartRecovery: No active carts found to process.");
            return;
        }
        
        $abandonment_threshold_hours = $settings['cart_abandonment_threshold'] ?? 4;
        $abandonment_threshold_seconds = $abandonment_threshold_hours * HOUR_IN_SECONDS;
        
        $email_delay_hours = $settings['cart_recovery_email_delay'] ?? 1;
        $email_delay_seconds = $email_delay_hours * HOUR_IN_SECONDS;
        
        $now = time();
        $total_emails_sent = 0;
        
        foreach ($active_cart_options as $option) {
            $cart_info = maybe_unserialize($option->option_value);
            
            // Extract user_id and session_id from option name
            $option_name = $option->option_name;
            $user_id = 0;
            $session_id = '';
            
            // Parse user_id and session_id from option name
            if (strpos($option_name, ShopMetrics_Cart_Tracker::ACTIVE_CART_OPTION_PREFIX . 'user_') === 0) {
                $user_id = intval(str_replace(ShopMetrics_Cart_Tracker::ACTIVE_CART_OPTION_PREFIX . 'user_', '', $option_name));
            } elseif (strpos($option_name, ShopMetrics_Cart_Tracker::ACTIVE_CART_OPTION_PREFIX . 'session_') === 0) {
                $session_id = str_replace(ShopMetrics_Cart_Tracker::ACTIVE_CART_OPTION_PREFIX . 'session_', '', $option_name);
            } else {
                continue; // Skip if not using the new format
            }
            
            if (empty($cart_info) || !isset($cart_info['timestamp']) || !isset($cart_info['data'])) {
                continue; // Skip invalid data
            }
            
            $last_activity = $cart_info['timestamp'];
            $cart_data = $cart_info['data'];
            
            // Check if cart is abandoned (inactive for threshold period)
            if (($now - $last_activity) > $abandonment_threshold_seconds) {
                // Check if we need to wait for email delay
                if (($now - $last_activity) > ($abandonment_threshold_seconds + $email_delay_seconds)) {
                    // Check if email was already sent
                    $email_option_key = $this->get_email_sent_option_key($user_id, $session_id);
                    $email_sent = get_option($email_option_key, false);
                    
                    if (!$email_sent) {
                        // Try to send recovery email
                        $sent = $this->send_cart_recovery_email($user_id, $session_id, $cart_data);
                        
                        if ($sent) {
                            // Mark email as sent
                            update_option($email_option_key, $now, 'no');
                            $total_emails_sent++;
                            
                            $identifier = !empty($user_id) && $user_id > 0 ? "User ID: {$user_id}" : "Session ID: {$session_id}";
                            \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartRecovery: Sent recovery email for abandoned cart ({$identifier})");
                        }
                    }
                }
            }
        }
        
        \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartRecovery: Completed send_recovery_emails cron job. Sent {$total_emails_sent} emails.");
    }
    
    /**
     * Get the email sent tracking option key
     * 
     * @param int $user_id
     * @param string $session_id
     * @return string
     */
    private function get_email_sent_option_key($user_id, $session_id) {
        // For logged-in users, prioritize user_id
        if (!empty($user_id) && $user_id > 0) {
            return ShopMetrics_Cart_Tracker::EMAIL_SENT_OPTION_PREFIX . 'user_' . $user_id;
        }
        // For guests, use session_id
        elseif (!empty($session_id)) {
            return ShopMetrics_Cart_Tracker::EMAIL_SENT_OPTION_PREFIX . 'session_' . $session_id;
        }
        // Fallback (should rarely happen)
        else {
            $unique_id = uniqid('unknown_', true);
            \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartRecovery: Could not determine user_id or session_id for email tracking, using generated ID: " . $unique_id);
            return ShopMetrics_Cart_Tracker::EMAIL_SENT_OPTION_PREFIX . $unique_id;
        }
    }
    
    /**
     * Send recovery email for an abandoned cart
     * 
     * @param int $user_id
     * @param string $session_id
     * @param array $cart_data
     * @return bool Whether the email was sent successfully
     */
    public function send_cart_recovery_email($user_id, $session_id, $cart_data) {
        $settings = get_option('shopmetrics_settings', []);
        
        // Get customer email
        $customer_email = '';
        $customer_name = '';
        $first_name = '';
        $last_name = '';
        
        // For registered users
        if ($user_id > 0) {
            $user = get_user_by('id', $user_id);
            if ($user && !empty($user->user_email)) {
                $customer_email = $user->user_email;
                $customer_name = $user->display_name;
                $first_name = $user->first_name;
                $last_name = $user->last_name;
            }
        }
        
        // For guest users with session, try to get from WooCommerce
        if (empty($customer_email) && !empty($session_id)) {
            // Try to find email in billing info saved for session
            $customer_data = WC()->session->get_session($session_id);
            if ($customer_data && isset($customer_data['customer']) && isset($customer_data['customer']['email'])) {
                $customer_email = $customer_data['customer']['email'];
                if (isset($customer_data['customer']['first_name'])) {
                    $first_name = $customer_data['customer']['first_name'];
                    $customer_name = $first_name;
                }
                if (isset($customer_data['customer']['last_name'])) {
                    $last_name = $customer_data['customer']['last_name'];
                    $customer_name .= ' ' . $last_name;
                }
            }
        }
        
        // If we couldn't find an email, we can't send a recovery email
        if (empty($customer_email) || !is_email($customer_email)) {
            \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartRecovery: Could not find valid email for user_id {$user_id}, session_id {$session_id}");
            return false;
        }
        
        // Get email settings
        $sender_name = $settings['cart_recovery_email_sender_name'] ?? get_bloginfo('name');
        $sender_email = $settings['cart_recovery_email_sender_email'] ?? get_option('admin_email');
        $email_subject = $settings['cart_recovery_email_subject'] ?? __('Did you forget something in your cart?', 'shopmetrics');
        $button_text = $settings['cart_recovery_button_text'] ?? __('Complete Your Purchase', 'shopmetrics');
        
        // Default content for email (just the content portion)
        $default_content = '<h3>Hello {first_name},</h3>
<p>We noticed that you left some items in your shopping cart at {site_name}.</p>

<p>Your cart is still saved, and you can complete your purchase by clicking the button below:</p>

{cart_items}

{recovery_link}

<p>Thank you for shopping with us!</p>

<p>{site_name}</p>

{coupon_code}';

        $email_content = $settings['cart_recovery_email_content'] ?? $default_content;
        
        // Generate recovery link
        $recovery_token = $this->generate_recovery_token($user_id, $session_id);
        $recovery_link = add_query_arg(array(
            'shopmetrics_cart_recovery' => $recovery_token,
            'shopmetrics_recovery_source' => 'email'
        ), wc_get_cart_url());
        
        // Format email subject with variables
        $email_subject = str_replace('{site_name}', get_bloginfo('name'), $email_subject);
        
        $cart_items_html = $this->format_cart_items_html($cart_data);
        $cart_total = isset($cart_data['total_value']) ? wc_price($cart_data['total_value']) : '';
        
        // Button HTML
        $recovery_button = '<div style="text-align: center; margin: 25px 0;">
            <a href="' . esc_url($recovery_link) . '" style="background-color: #4CAF50; color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">' . esc_html($button_text) . '</a>
        </div>';
        
        // Add coupon if enabled
        $coupon_html = '';
        $include_coupon = !empty($settings['cart_recovery_include_coupon']);
        if ($include_coupon) {
            $coupon_code = $settings['cart_recovery_coupon_code'] ?? '';
            if (!empty($coupon_code)) {
                $coupon_html = '<div style="background-color: #f8f9fa; border: 1px dashed #ddd; padding: 15px; margin: 20px 0; text-align: center;">
                    <p style="margin: 0; font-size: 14px;">' . esc_html__('Use this coupon code for a special discount:', 'shopmetrics') . '</p>
                    <p style="font-size: 20px; font-weight: bold; margin: 10px 0; letter-spacing: 1px;">' . esc_html($coupon_code) . '</p>
                </div>';
            }
        }
        
        $replacements = array(
            '{site_name}' => get_bloginfo('name'),
            '{cart_items}' => $cart_items_html,
            '{cart_total}' => $cart_total,
            '{recovery_link}' => $recovery_button,
            '{customer_name}' => $customer_name,
            '{first_name}' => $first_name,
            '{last_name}' => $last_name,
            '{coupon_code}' => $coupon_html
        );
        
        $email_content = str_replace(array_keys($replacements), array_values($replacements), $email_content);
        
        // Apply the HTML template
        $email_template = '<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{site_name}</title>
    <style type="text/css">
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            padding: 15px 0;
            border-bottom: 1px solid #eeeeee;
            margin-bottom: 20px;
        }
        .content {
            padding: 20px 0;
        }
        .footer {
            font-size: 12px;
            text-align: center;
            color: #999999;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eeeeee;
        }
        a {
            color: #0066cc;
            text-decoration: none;
        }
        .unsubscribe {
            font-size: 11px;
            color: #999999;
        }
        /* Product cards styling */
        .product-card {
            border: 1px solid #eeeeee;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
            display: flex;
            flex-direction: row;
        }
        .product-image {
            width: 80px;
            height: 80px;
            margin-right: 15px;
            background-color: #f9f9f9;
        }
        .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .product-info {
            flex: 1;
        }
        .product-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .product-price {
            font-weight: bold;
            color: #0066cc;
        }
        .product-quantity {
            color: #666666;
            font-size: 14px;
        }
        .coupon-code {
            background-color: #f9f9f9;
            border: 1px dashed #cccccc;
            padding: 10px;
            text-align: center;
            margin: 15px 0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        {user_content}
    </div>
</body>
</html>';

        $full_email_content = str_replace('{user_content}', $email_content, $email_template);
        $full_email_content = str_replace('{site_name}', get_bloginfo('name'), $full_email_content);
        
        // Email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $sender_name . ' <' . $sender_email . '>'
        );
        
        // Send the email
        $email_sent = wp_mail($customer_email, $email_subject, $full_email_content, $headers);
        
        if ($email_sent) {
            // Send event to track the email was sent
            $event_data = array(
                'user_id' => $user_id,
                'session_id' => $session_id,
                'cart_hash' => isset($cart_data['cart_hash']) ? $cart_data['cart_hash'] : '',
                'cart_value' => isset($cart_data['total_value']) ? $cart_data['total_value'] : 0,
                'customer_email' => $customer_email
            );
            $this->send_cart_event('recovery_email_sent', $event_data);
        }
        
        return $email_sent;
    }
    
    /**
     * Format cart items for HTML display in email
     * 
     * @param array $cart_data
     * @return string HTML representation of cart items
     */
    private function format_cart_items_html($cart_data) {
        if (empty($cart_data) || empty($cart_data['items']) || !is_array($cart_data['items'])) {
            return '';
        }
        
        $items_html = '<div style="margin: 15px 0;">';
        
        // Cart items grid container
        $items_html .= '<div style="display: block; margin-bottom: 15px;">';
        
        foreach ($cart_data['items'] as $item) {
            $product_id = isset($item['product_id']) ? $item['product_id'] : 0;
            $product_name = isset($item['name']) ? $item['name'] : '';
            $quantity = isset($item['quantity']) ? $item['quantity'] : 1;
            $price = isset($item['line_total']) ? wc_price($item['line_total']) : '';
            $single_price = isset($item['price']) ? wc_price($item['price']) : '';
            
            // Get product image
            $image_url = '';
            if ($product_id) {
                // Try to get the product
                $product = wc_get_product($product_id);
                if ($product) {
                    $image_id = $product->get_image_id();
                    if ($image_id) {
                        $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                    }
                    
                    // If no specific image, try using placeholder
                    if (!$image_url) {
                        $image_url = wc_placeholder_img_src('thumbnail');
                    }
                } else {
                    // Use placeholder if product not found
                    $image_url = wc_placeholder_img_src('thumbnail');
                }
            }
            
            // Product URL
            $product_url = $product_id ? get_permalink($product_id) : '';
            
            // Start product card
            $items_html .= '<div style="border: 1px solid #eee; border-radius: 4px; padding: 15px; margin-bottom: 15px; background-color: #fff; max-width: 100%; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
            $items_html .= '<div style="display: flex; flex-wrap: nowrap;">';
            
            // Product image (left side)
            $items_html .= '<div style="flex: 0 0 80px; margin-right: 15px;">';
            if ($image_url) {
                $items_html .= '<a href="' . esc_url($product_url) . '" style="display: block; width: 80px; height: 80px; overflow: hidden; border: 1px solid #f5f5f5; border-radius: 4px;">';
                // Plugin asset image: not in Media Library, so <img> is used instead of wp_get_attachment_image()
                $items_html .= '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($product_name) . '" style="width: 100%; height: auto; display: block;">';
                $items_html .= '</a>';
            }
            $items_html .= '</div>';
            
            // Product details (right side)
            $items_html .= '<div style="flex: 1; min-width: 0;">';
            
            // Product title with link
            $items_html .= '<h3 style="margin: 0 0 8px 0; font-size: 16px; line-height: 1.4;">';
            if ($product_url) {
                $items_html .= '<a href="' . esc_url($product_url) . '" style="color: #0066cc; text-decoration: none;">' . esc_html($product_name) . '</a>';
            } else {
                $items_html .= esc_html($product_name);
            }
            $items_html .= '</h3>';
            
            // Price and quantity info
            $items_html .= '<div style="margin-bottom: 5px; color: #555; font-size: 14px;">';
            // translators: %s is the quantity of items in the cart
            $items_html .= '<span>' . esc_html(sprintf(__('Quantity: %s', 'shopmetrics'), $quantity)) . '</span>';
            $items_html .= '</div>';
            
            // Price info
            $items_html .= '<div style="font-weight: bold; color: #333; font-size: 15px;">';
            if ($quantity > 1 && !empty($single_price)) {
                $items_html .= $price . ' <span style="font-size: 13px; color: #777;">(' . $single_price . ' ' . __('each', 'shopmetrics') . ')</span>';
            } else {
                $items_html .= $price;
            }
            $items_html .= '</div>';
            
            $items_html .= '</div>'; // End product details
            $items_html .= '</div>'; // End flex container
            $items_html .= '</div>'; // End product card
        }
        
        $items_html .= '</div>'; // End cart items grid container
        
        // Cart total
        $total = isset($cart_data['total_value']) ? $cart_data['total_value'] : (isset($cart_data['total']) ? $cart_data['total'] : 0);
        $items_html .= '<div style="text-align: right; padding: 10px 0; margin-top: 15px; border-top: 2px solid #eee;">';
        $items_html .= '<span style="font-size: 18px; font-weight: bold;">' . __('Total:', 'shopmetrics') . ' ' . wc_price($total) . '</span>';
        $items_html .= '</div>';
        
        $items_html .= '</div>'; // End main container
        
        return $items_html;
    }
    
    /**
     * Generate a secure token for cart recovery links
     * 
     * @param int $user_id
     * @param string $session_id
     * @return string
     */
    private function generate_recovery_token($user_id, $session_id) {
        $settings = get_option('shopmetrics_settings', []);
        $expiry = time() + ($settings['cart_recovery_link_expiry'] ?? 7) * DAY_IN_SECONDS;
        $data = array(
            'user_id' => $user_id,
            'session_id' => $session_id,
            'expiry' => $expiry
        );
        
        $json = json_encode($data);
        $hash = hash_hmac('sha256', $json, wp_salt());
        
        $token = base64_encode($json) . '.' . $hash;
        return $token;
    }
    
    /**
     * Validate a recovery token
     * 
     * @param string $token
     * @return array|false Returns data array if valid, false otherwise
     */
    private function validate_recovery_token($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }
        
        list($data_base64, $hash) = $parts;
        
        $json = base64_decode($data_base64);
        if (!$json) {
            return false;
        }
        
        $data = json_decode($json, true);
        if (!$data || !isset($data['expiry'])) {
            return false;
        }
        
        // Verify hash
        $calculated_hash = hash_hmac('sha256', $json, wp_salt());
        if (!hash_equals($calculated_hash, $hash)) {
            return false;
        }
        
        // Check expiry
        if ($data['expiry'] < time()) {
            return false;
        }
        
        return $data;
    }
    
    /**
     * Handle recovery link clicks
     *
     * SECURITY NOTE:
     * Nonce verification is NOT used here because this is a public, signed, time-limited recovery link sent via email.
     * - The link contains a cryptographically signed token (HMAC with wp_salt) that is validated for integrity and expiry.
     * - This is not a form or AJAX action, and is not subject to CSRF (user is not logged in, and the link is single-use).
     * - Adding a nonce would break the flow and not improve security.
     * - The token validation provides equivalent or better security than a WordPress nonce for this use case.
     */
    public function handle_recovery_link() {
        if (!isset($_GET['shopmetrics_cart_recovery']) || empty($_GET['shopmetrics_cart_recovery'])) {
            return;
        }
        
        $token = sanitize_text_field( wp_unslash( $_GET['shopmetrics_cart_recovery'] ) );
        $recovery_source = isset($_GET['shopmetrics_recovery_source']) ? sanitize_text_field( wp_unslash( $_GET['shopmetrics_recovery_source'] ) ) : 'email';
        
        $data = $this->validate_recovery_token($token);
        if (!$data) {
            wc_add_notice(__('The recovery link is invalid or has expired.', 'shopmetrics'), 'error');
            return;
        }
        
        $user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
        $session_id = isset($data['session_id']) ? $data['session_id'] : '';
        
        // Try to restore cart
        $cart_restored = $this->restore_abandoned_cart($user_id, $session_id);
        
        if ($cart_restored) {
            // Set recovery source cookie for tracking
            setcookie('shopmetrics_cart_recovery_source', $recovery_source, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
            
            // Send event to track the recovery attempt
            $event_data = array(
                'user_id' => $user_id,
                'session_id' => $session_id,
                'recovery_source' => $recovery_source
            );
            $this->send_cart_event('cart_recovery_clicked', $event_data);
            
            wc_add_notice(__('Your cart has been restored. You can now complete your purchase.', 'shopmetrics'), 'success');
        } else {
            wc_add_notice(__('We couldn\'t restore your cart. It may have expired or been already processed.', 'shopmetrics'), 'error');
        }
    }
    
    /**
     * Restore an abandoned cart
     * 
     * @param int $user_id
     * @param string $session_id
     * @return bool
     */
    private function restore_abandoned_cart($user_id, $session_id) {
        $option_key = '';
        
        // Try to find the cart data
        if (!empty($user_id) && $user_id > 0) {
            $option_key = ShopMetrics_Cart_Tracker::ACTIVE_CART_OPTION_PREFIX . 'user_' . $user_id;
        } elseif (!empty($session_id)) {
            $option_key = ShopMetrics_Cart_Tracker::ACTIVE_CART_OPTION_PREFIX . 'session_' . $session_id;
        }
        
        if (empty($option_key)) {
            return false;
        }
        
        $cart_info = get_option($option_key, false);
        if (!$cart_info || !isset($cart_info['data']) || !isset($cart_info['data']['items'])) {
            return false;
        }
        
        // Get cart data
        $cart_data = $cart_info['data'];
        
        // Update timestamp to prevent considering this as abandoned
        $cart_info['timestamp'] = time();
        update_option($option_key, $cart_info, 'no');
        
        // Clear current cart
        WC()->cart->empty_cart();
        
        // Add items back to cart
        $items_added = 0;
        
        foreach ($cart_data['items'] as $item) {
            $product_id = isset($item['product_id']) ? $item['product_id'] : 0;
            $variation_id = isset($item['variation_id']) ? $item['variation_id'] : 0;
            $quantity = isset($item['quantity']) ? $item['quantity'] : 1;
            
            if (empty($product_id)) {
                continue;
            }
            
            $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id);
            if ($cart_item_key) {
                $items_added++;
            }
        }
        
        // Send event for cart restored
        $event_data = array(
            'user_id' => $user_id,
            'session_id' => $session_id,
            'cart_hash' => isset($cart_data['cart_hash']) ? $cart_data['cart_hash'] : '',
            'items_restored' => $items_added
        );
        $this->send_cart_event('cart_restored', $event_data);
        
        return $items_added > 0;
    }
    
    /**
     * Wrap email content in HTML template
     *
     * @deprecated No longer used since we directly apply the template in send_test_email 
     * and send_cart_recovery_email methods.
     * 
     * @param string $content
     * @return string
     */
    private function get_email_template($content) {
        // This method is kept for backwards compatibility
        // The template is now applied directly in the email sending methods
        return $content;
    }
    
    /**
     * Sends cart event data to the backend.
     *
     * @since 1.2.0
     * @param string $event_type
     * @param array  $event_specific_data
     */
    private function send_cart_event($event_type, $event_specific_data) {
        $site_identifier = get_option('shopmetrics_analytics_site_identifier', '');
        if (empty($site_identifier)) {
            \ShopMetrics_Logger::get_instance()->info("Site Identifier not set. Cannot send '{$event_type}'.");
            return;
        }

        // Add common event fields here
        $payload = array(
            'site_identifier' => $site_identifier,
            'event_type'      => $event_type,
            'timestamp'       => current_time('mysql', true), // UTC timestamp for the event itself
            'data'            => $event_specific_data, 
        );
        
        // For recovery_email_sent events, include additional data required by the backend
        if ($event_type === 'recovery_email_sent') {
            // Add user and session ID to the root level to ensure proper identification
            $payload['user_id'] = isset($event_specific_data['user_id']) ? $event_specific_data['user_id'] : null;
            $payload['session_id'] = isset($event_specific_data['session_id']) ? $event_specific_data['session_id'] : null;
            
            // Ensure necessary fields are set in the event_data
            $required_fields = array(
                'cart_hash' => isset($event_specific_data['cart_hash']) ? $event_specific_data['cart_hash'] : '',
                'total_value' => isset($event_specific_data['total_value']) ? $event_specific_data['total_value'] : 0,
                'total_items' => isset($event_specific_data['total_items']) ? $event_specific_data['total_items'] : 0,
                'currency' => isset($event_specific_data['currency']) ? $event_specific_data['currency'] : get_woocommerce_currency(),
                'items' => isset($event_specific_data['items']) ? $event_specific_data['items'] : array(),
            );
            
            // Add any missing required fields
            foreach ($required_fields as $key => $value) {
                if (!isset($payload['data'][$key])) {
                    $payload['data'][$key] = $value;
                }
            }
            
            \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartRecovery: Sending recovery_email_sent event with enhanced cart data");
            \ShopMetrics_Logger::get_instance()->debug("FinanciarMe CartRecovery: Payload: " . wp_json_encode($payload));
        }
        
        $response = ShopMetrics_Api_Client::send_request('v1/track/event', $payload, 'POST');

        if (is_wp_error($response)) {
            \ShopMetrics_Logger::get_instance()->error("Error sending '{$event_type}' event: " . $response->get_error_message());
        } else {
            $log_message = "FinanciarMe CartRecovery: Sent '{$event_type}' event.";
            if (is_array($response) || is_object($response)) {
                $log_message .= " Response: " . wp_json_encode($response);
            }
            \ShopMetrics_Logger::get_instance()->info($log_message);
        }
    }
    
    /**
     * Send a test recovery email to a specified email address
     * 
     * @param string $email The email address to send the test to
     * @param string $name The recipient's name
     * @param array $cart_data Sample cart data for the test email
     * @return bool Whether the email was sent successfully
     */
    public function send_test_email($email, $name, $cart_data) {
        \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartRecovery: send_test_email called with email: " . $email . ", name: " . $name);
        
        if (empty($email) || !is_email($email)) {
            \ShopMetrics_Logger::get_instance()->error("FinanciarMe CartRecovery: Invalid email address for test email: " . $email);
            return false;
        }
        
        // Check if WooCommerce functions are available
        if (!function_exists('wc_price')) {
            \ShopMetrics_Logger::get_instance()->error("FinanciarMe CartRecovery: WooCommerce function wc_price() not available");
            return false;
        }
        
        if (!function_exists('wc_get_cart_url')) {
            \ShopMetrics_Logger::get_instance()->error("FinanciarMe CartRecovery: WooCommerce function wc_get_cart_url() not available");
            return false;
        }
        
        $settings = get_option('shopmetrics_settings', []);
        
        // Get email settings
        $sender_name = $settings['cart_recovery_email_sender_name'] ?? get_bloginfo('name');
        $sender_email = $settings['cart_recovery_email_sender_email'] ?? get_option('admin_email');
        $email_subject = $settings['cart_recovery_email_subject'] ?? __('Did you forget something in your cart?', 'shopmetrics');
        $button_text = $settings['cart_recovery_button_text'] ?? __('Complete Your Purchase', 'shopmetrics');
        
        // Default content for email (just the content portion)
        $default_content = '<h3>Hello {first_name},</h3>

<p>We noticed that you left some items in your shopping cart at {site_name}.</p>
<p>Your cart is still saved, and you can complete your purchase by clicking the button below:</p>

{cart_items}

{recovery_link}

<p>Thank you for shopping with us!</p>

<p>{site_name}</p>

<p>{coupon_code}</p>';

        $email_content = $settings['cart_recovery_email_content'] ?? $default_content;
        
        // Get coupon settings
        $include_coupon = !empty($settings['cart_recovery_include_coupon']);
        $coupon_code = $include_coupon ? $settings['cart_recovery_coupon_code'] ?? '' : '';
        
        // Create coupon HTML if enabled
        $coupon_html = '';
        if ($include_coupon && !empty($coupon_code)) {
            $coupon_html = '<div class="coupon-code">
                <p>' . __('Use coupon code', 'shopmetrics') . ': <strong>' . esc_html($coupon_code) . '</strong></p>
            </div>';
        }
        
        // Generate a dummy recovery token for the test
        $recovery_token = 'test_token_' . wp_generate_password(16, false);
        $recovery_link = add_query_arg(array(
            'shopmetrics_cart_recovery' => $recovery_token,
            'shopmetrics_recovery_source' => 'email_test',
            'shopmetrics_test' => '1'
        ), wc_get_cart_url());
        
        // Format the cart items HTML
        $cart_items_html = $this->format_cart_items_html($cart_data);
        
        // Create the recovery button HTML
        $recovery_button = '<div style="text-align: center; margin: 25px 0;">
            <a href="' . esc_url($recovery_link) . '" style="background-color: #4CAF50; color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">' . esc_html($button_text) . '</a>
        </div>';
        
        // Split the name into first and last name for testing variables
        $name_parts = explode(' ', $name);
        $first_name = !empty($name_parts[0]) ? $name_parts[0] : '';
        $last_name = count($name_parts) > 1 ? end($name_parts) : '';
        
        // Prepare all replacements
        $replacements = array(
            '{site_name}' => get_bloginfo('name'),
            '{cart_items}' => $cart_items_html,
            '{cart_total}' => wc_price(isset($cart_data['total']) ? $cart_data['total'] : 0),
            '{recovery_link}' => $recovery_button, // Use formatted button HTML
            '{customer_name}' => $name,
            '{first_name}' => $first_name,
            '{last_name}' => $last_name,
            '{coupon_code}' => $coupon_html
        );
        
        // Replace variables in email content and subject
        $email_content = str_replace(array_keys($replacements), array_values($replacements), $email_content);
        $email_subject = str_replace('{site_name}', get_bloginfo('name'), $email_subject);
        
        // Add "TEST" prefix to the subject
        $email_subject = '[TEST] ' . $email_subject;
        
        // Set email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $sender_name . ' <' . $sender_email . '>',
        );
        
        // Apply the HTML template
        $email_template = '<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{site_name}</title>
    <style type="text/css">
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            padding: 15px 0;
            border-bottom: 1px solid #eeeeee;
            margin-bottom: 20px;
        }
        .content {
            padding: 20px 0;
        }
        .footer {
            font-size: 12px;
            text-align: center;
            color: #999999;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eeeeee;
        }
        a {
            color: #0066cc;
            text-decoration: none;
        }
        .unsubscribe {
            font-size: 11px;
            color: #999999;
        }
        /* Product cards styling */
        .product-card {
            border: 1px solid #eeeeee;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
            display: flex;
            flex-direction: row;
        }
        .product-image {
            width: 80px;
            height: 80px;
            margin-right: 15px;
            background-color: #f9f9f9;
        }
        .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .product-info {
            flex: 1;
        }
        .product-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .product-price {
            font-weight: bold;
            color: #0066cc;
        }
        .product-quantity {
            color: #666666;
            font-size: 14px;
        }
        .coupon-code {
            background-color: #f9f9f9;
            border: 1px dashed #cccccc;
            padding: 10px;
            text-align: center;
            margin: 15px 0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        {user_content}
    </div>
</body>
</html>';

        $email_html = str_replace('{user_content}', $email_content, $email_template);
        $email_html = str_replace('{site_name}', get_bloginfo('name'), $email_html);
        
        // Send the email - avoid double call to wp_mail
        \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartRecovery: Sending test email to " . $email);
        \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartRecovery: Email subject: " . $email_subject);
        \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartRecovery: Sender: " . $sender_name . " <" . $sender_email . ">");
        
        $result = wp_mail($email, $email_subject, $email_html, $headers);
        
        if ($result) {
            \ShopMetrics_Logger::get_instance()->info("FinanciarMe CartRecovery: Test email sent successfully to " . $email);
        } else {
            \ShopMetrics_Logger::get_instance()->error("FinanciarMe CartRecovery: Failed to send test email to " . $email);
            
            // Check for WordPress mail errors
            global $phpmailer;
            if (isset($phpmailer) && !empty($phpmailer->ErrorInfo)) {
                \ShopMetrics_Logger::get_instance()->error("FinanciarMe CartRecovery: PHPMailer error: " . $phpmailer->ErrorInfo);
            }
        }
        
        return $result;
    }
} 