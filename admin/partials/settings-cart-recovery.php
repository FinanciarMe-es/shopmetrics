<?php
/**
 * Cart Recovery Settings form.
 *
 * @link       https://shopmetrics.es
 * @since      1.2.0
 *
 * @package    ShopMetrics
 * @subpackage ShopMetrics/admin/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}
?>

<div class="wrap">    
    <form method="post" action="options.php" id="sm-cart-recovery-unified-form">
        <?php settings_fields('shopmetrics_settings_group'); ?>

        <!-- Hidden fields to ensure checkboxes are properly handled -->
        <input type="hidden" name="shopmetrics_settings[enable_cart_recovery_emails]" value="0" />
        <input type="hidden" name="shopmetrics_settings[cart_recovery_include_coupon]" value="0" />
        
        <!-- Hidden fields to preserve connection settings (stored as separate options) -->
        <input type="hidden" name="shopmetrics_analytics_api_token" value="<?php echo esc_attr(get_option('shopmetrics_analytics_api_token', '')); ?>" />
        <input type="hidden" name="shopmetrics_analytics_site_identifier" value="<?php echo esc_attr(get_option('shopmetrics_analytics_site_identifier', '')); ?>" />

        <div class="sm-settings-section">
            <h2 style="display: flex; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                <span class="dashicons dashicons-cart" style="font-size: 24px; width: 24px; height: 24px; margin-right: 10px; color: #2271b1;"></span>
                <?php echo esc_html__( 'Abandoned Cart Recovery', 'shopmetrics' ); ?>
            </h2>
            
            <p class="description">
                <?php echo esc_html__( 'Configure settings for tracking and recovering abandoned carts.', 'shopmetrics' ); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="shopmetrics_settings[cart_abandonment_threshold]">
                            <?php echo esc_html__( 'Cart Abandonment Threshold (hours)', 'shopmetrics' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               id="shopmetrics_settings[cart_abandonment_threshold]" 
                               name="shopmetrics_settings[cart_abandonment_threshold]" 
                               value="<?php echo esc_attr( !empty($settings['cart_abandonment_threshold']) ? $settings['cart_abandonment_threshold'] : 4 ); ?>"
                               min="1" 
                               max="72" 
                               step="1" />
                        <p class="description">
                            <?php echo esc_html__( 'Number of hours of inactivity before a cart is considered abandoned.', 'shopmetrics' ); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="shopmetrics_settings[enable_cart_recovery_emails]">
                            <?php echo esc_html__( 'Enable Recovery Emails', 'shopmetrics' ); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="shopmetrics_settings[enable_cart_recovery_emails]" 
                                   name="shopmetrics_settings[enable_cart_recovery_emails]" 
                                   value="1" 
                                   <?php checked(!empty($settings['enable_cart_recovery_emails'])); ?> />
                            <?php echo esc_html__( 'Send email notifications for abandoned carts', 'shopmetrics' ); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="shopmetrics_settings[cart_recovery_email_delay]">
                            <?php echo esc_html__( 'Email Delay (hours)', 'shopmetrics' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               id="shopmetrics_settings[cart_recovery_email_delay]" 
                               name="shopmetrics_settings[cart_recovery_email_delay]" 
                               value="<?php echo esc_attr( !empty($settings['cart_recovery_email_delay']) ? $settings['cart_recovery_email_delay'] : 1 ); ?>"
                               min="0.5" 
                               max="72" 
                               step="0.5" />
                        <p class="description">
                            <?php echo esc_html__( 'How many hours to wait after cart abandonment before sending the recovery email.', 'shopmetrics' ); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="shopmetrics_settings[cart_recovery_include_coupon]">
                            <?php echo esc_html__( 'Include Coupon', 'shopmetrics' ); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="shopmetrics_settings[cart_recovery_include_coupon]" 
                                   name="shopmetrics_settings[cart_recovery_include_coupon]" 
                                   value="1" 
                                   <?php checked(!empty($settings['cart_recovery_include_coupon'])); ?> />
                            <?php echo esc_html__( 'Include a coupon code in recovery emails', 'shopmetrics' ); ?>
                        </label>
                    </td>
                </tr>

                <tr class="shopmetrics-coupon-field" style="<?php echo !empty($settings['cart_recovery_include_coupon']) ? '' : 'display: none;'; ?>">
                    <th scope="row">
                        <label for="shopmetrics_settings[cart_recovery_coupon_code]">
                            <?php echo esc_html__( 'Coupon Code', 'shopmetrics' ); ?>
                        </label>
                    </th>
                    <td>
                        <select id="shopmetrics_settings[cart_recovery_coupon_code]" 
                                name="shopmetrics_settings[cart_recovery_coupon_code]" 
                                class="regular-text">
                            <option value=""><?php echo esc_html__( '-- Select a coupon --', 'shopmetrics' ); ?></option>
                            <?php
                            // Get all available coupons
                            $coupons = get_posts( array(
                                'post_type'      => 'shop_coupon',
                                'posts_per_page' => -1,
                                'post_status'    => 'publish',
                            ) );

                            $selected_coupon = !empty($settings['cart_recovery_coupon_code']) ? $settings['cart_recovery_coupon_code'] : '';

                            foreach ( $coupons as $coupon ) {
                                echo '<option value="' . esc_attr( $coupon->post_title ) . '" ' . selected( $selected_coupon, $coupon->post_title, false ) . '>' . esc_html( $coupon->post_title ) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description">
                            <?php echo esc_html__( 'Select an existing WooCommerce coupon to include in recovery emails.', 'shopmetrics' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="sm-settings-section">
            <h2 style="display: flex; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                <span class="dashicons dashicons-email" style="font-size: 24px; width: 24px; height: 24px; margin-right: 10px; color: #2271b1;"></span>
                <?php echo esc_html__( 'Recovery Email Content', 'shopmetrics' ); ?>
            </h2>
            
            <p class="description">
                <?php echo esc_html__( 'Customize the email message sent to customers with abandoned carts.', 'shopmetrics' ); ?>
            </p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="shopmetrics_settings[cart_recovery_email_sender_name]">
                            <?php echo esc_html__( 'From Name', 'shopmetrics' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" 
                               id="shopmetrics_settings[cart_recovery_email_sender_name]" 
                               name="shopmetrics_settings[cart_recovery_email_sender_name]" 
                               value="<?php echo esc_attr( !empty($settings['cart_recovery_email_sender_name']) ? $settings['cart_recovery_email_sender_name'] : get_bloginfo( 'name' ) ); ?>"
                               class="regular-text" />
                        <p class="description">
                            <?php echo esc_html__( 'The name that appears in the "From" field of recovery emails.', 'shopmetrics' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="shopmetrics_settings[cart_recovery_email_sender_email]">
                            <?php echo esc_html__( 'From Email', 'shopmetrics' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="email" 
                               id="shopmetrics_settings[cart_recovery_email_sender_email]" 
                               name="shopmetrics_settings[cart_recovery_email_sender_email]" 
                               value="<?php echo esc_attr( !empty($settings['cart_recovery_email_sender_email']) ? $settings['cart_recovery_email_sender_email'] : get_option( 'admin_email' ) ); ?>"
                               class="regular-text" />
                        <p class="description">
                            <?php echo esc_html__( 'The email address that appears in the "From" field of recovery emails.', 'shopmetrics' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="shopmetrics_settings[cart_recovery_email_subject]">
                            <?php echo esc_html__( 'Email Subject', 'shopmetrics' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" 
                               id="shopmetrics_settings[cart_recovery_email_subject]" 
                               name="shopmetrics_settings[cart_recovery_email_subject]" 
                               value="<?php echo esc_attr( !empty($settings['cart_recovery_email_subject']) ? $settings['cart_recovery_email_subject'] : __( 'Did you forget something in your cart?', 'shopmetrics' ) ); ?>"
                               class="regular-text" />
                        <p class="description">
                            <?php echo esc_html__( 'Available variables: {site_name}', 'shopmetrics' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="shopmetrics_settings[cart_recovery_button_text]">
                            <?php echo esc_html__( 'Recovery Button Text', 'shopmetrics' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" 
                               id="shopmetrics_settings[cart_recovery_button_text]" 
                               name="shopmetrics_settings[cart_recovery_button_text]" 
                               value="<?php echo esc_attr( !empty($settings['cart_recovery_button_text']) ? $settings['cart_recovery_button_text'] : __( 'Complete Your Purchase', 'shopmetrics' ) ); ?>"
                               class="regular-text" />
                        <p class="description">
                            <?php echo esc_html__( 'Text displayed on the recovery button in the email.', 'shopmetrics' ); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="shopmetrics_settings[cart_recovery_link_expiry]">
                            <?php echo esc_html__( 'Recovery Link Expiry (days)', 'shopmetrics' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               id="shopmetrics_settings[cart_recovery_link_expiry]" 
                               name="shopmetrics_settings[cart_recovery_link_expiry]" 
                               value="<?php echo esc_attr( !empty($settings['cart_recovery_link_expiry']) ? $settings['cart_recovery_link_expiry'] : 7 ); ?>"
                               min="1" 
                               max="30" 
                               step="1" />
                        <p class="description">
                            <?php echo esc_html__( 'Number of days before the recovery link expires.', 'shopmetrics' ); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="shopmetrics_settings[cart_recovery_email_content]">
                            <?php echo esc_html__( 'Email Content', 'shopmetrics' ); ?>
                        </label>
                    </th>
                    <td>
                        <?php
                        $default_content = "Hello,\n\nWe noticed that you left some items in your shopping cart at {site_name}.\n\nYour cart is still saved, and you can complete your purchase by clicking the link below:\n\n{cart_items}\n\n{recovery_link}\n\nThank you for shopping with us!\n\n{site_name}";
                        wp_editor(
                            !empty($settings['cart_recovery_email_content']) ? $settings['cart_recovery_email_content'] : $default_content,
                            'shopmetrics_settings[cart_recovery_email_content]',
                            array(
                                'textarea_name' => 'shopmetrics_settings[cart_recovery_email_content]',
                                'textarea_rows' => 10,
                                'media_buttons' => false,
                                'editor_class'  => 'sm-html-editor',
                            )
                        );
                        ?>
                        <p class="description">
                            <?php echo esc_html__( 'Available variables: {site_name}, {cart_items}, {cart_total}, {recovery_link}, {customer_name}, {first_name}, {last_name}, {coupon_code}', 'shopmetrics' ); ?>
                        </p>
                        
                        <div class="sm-info-box" style="margin-top: 15px; background-color: #f8f9fa; border-left: 4px solid #0073aa; padding: 12px;">
                            <h4 style="margin-top: 0;"><?php echo esc_html__( 'HTML Email Customization', 'shopmetrics' ); ?></h4>
                            <p>
                                <?php echo esc_html__( 'You have full control over the HTML structure of your emails, including the header and footer. The editor above allows you to customize the entire email template.', 'shopmetrics' ); ?>
                            </p>
                            <p>
                                <?php echo esc_html__( 'The template includes responsive styling suitable for most email clients. If you make changes to the HTML structure, be sure to test your emails thoroughly.', 'shopmetrics' ); ?>
                            </p>
                        </div>
                        
                        <div class="sm-info-box" style="margin-top: 15px; background-color: #f8f9fa; border-left: 4px solid #2271b1; padding: 12px;">
                            <h4 style="margin-top: 0;"><?php echo esc_html__( 'About {cart_items} Display', 'shopmetrics' ); ?></h4>
                            <p>
                                <?php echo esc_html__( 'Cart items are now displayed as attractive product cards with:', 'shopmetrics' ); ?>
                            </p>
                            <ul style="margin-left: 20px; list-style-type: disc;">
                                <li><?php echo esc_html__( 'Product image (when available)', 'shopmetrics' ); ?></li>
                                <li><?php echo esc_html__( 'Product title', 'shopmetrics' ); ?></li>
                                <li><?php echo esc_html__( 'Price and quantity information', 'shopmetrics' ); ?></li>
                                <li><?php echo esc_html__( 'Clickable links to product pages', 'shopmetrics' ); ?></li>
                            </ul>
                            <p>
                                <?php echo esc_html__( 'This visual presentation helps customers recognize their items more easily and increases the likelihood of cart recovery.', 'shopmetrics' ); ?>
                            </p>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="sm-settings-section">
            <h2 style="display: flex; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                <span class="dashicons dashicons-admin-tools" style="font-size: 24px; width: 24px; height: 24px; margin-right: 10px; color: #2271b1;"></span>
                <?php echo esc_html__( 'Test Email', 'shopmetrics' ); ?>
            </h2>
            
            <p class="description">
                <?php echo esc_html__( 'Send a test recovery email to verify how notifications appear to your customers. This helps ensure that your recovery emails look professional and contain all necessary information.', 'shopmetrics' ); ?>
            </p>
            
            <div style="margin-top: 15px;">
                <?php wp_nonce_field('sm_settings_ajax_nonce', 'sm_settings_nonce'); // Add nonce field for AJAX requests ?>
                <button type="button" id="shopmetrics_test_recovery_email" class="button button-secondary">
                    <?php echo esc_html__( 'Send Test Email', 'shopmetrics' ); ?>
                </button>
                <span id="shopmetrics_test_email_result" style="margin-left: 10px; display: none;"></span>
            </div>
            
            <div class="sm-info-box" style="margin-top: 15px; background-color: #f8f9fa; border-left: 4px solid #ffc107; padding: 12px;">
                <p>
                    <?php echo esc_html__( 'The test email will be sent to your WordPress admin email address with sample cart data to preview the layout and formatting.', 'shopmetrics' ); ?>
                </p>
            </div>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Toggle coupon fields based on checkbox
        $('#shopmetrics_settings[cart_recovery_include_coupon]').change(function() {
            if ($(this).is(':checked')) {
                $('.shopmetrics-coupon-field').show();
            } else {
                $('.shopmetrics-coupon-field').hide();
            }
        });
        
                        // Test email AJAX is handled in admin/js/shopmetrics-settings.js
        // to avoid duplicate email sends
    });
</script> 