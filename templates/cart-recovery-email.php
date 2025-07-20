<?php
/**
 * Cart Recovery Email Template
 *
 * This template is used to generate HTML emails for abandoned cart recovery.
 * Variables that can be used in this template:
 * {site_name} - Name of the website
 * {customer_name} - Name of the customer (if available)
 * {cart_items} - HTML table of cart items
 * {cart_total} - Total value of the cart
 * {recovery_link} - Button with link to recover the cart
 * {coupon_code} - Coupon code section (if enabled)
 *
 * @link       https://shopmetrics.es
 * @since      1.2.0
 *
 * @package    ShopMetrics
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title><?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
    <!--
        Inline CSS is required for email client compatibility.
        This <style> block is only used in the generated email HTML, not in the WordPress admin or frontend.
        Per WP.org guidelines, wp_enqueue_style is not used for email templates, as most email clients do not support external stylesheets.
        See: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#6-services-calls-home
    -->
    <style type="text/css">
        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            color: #333333;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .header {
            padding: 20px;
            text-align: center;
            background-color: #f8f9fa;
            border-top-left-radius: 5px;
            border-top-right-radius: 5px;
            border-bottom: 1px solid #eeeeee;
        }
        .content {
            padding: 30px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            padding: 12px;
            text-align: left;
            background-color: #f8f9fa;
            border-bottom: 1px solid #dddddd;
        }
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #eeeeee;
        }
        .items-table .price {
            text-align: right;
        }
        .items-table .quantity {
            text-align: center;
        }
        .cart-total {
            text-align: right;
            font-weight: bold;
            margin-top: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 3px;
        }
        .btn-recovery {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 12px 24px;
            text-align: center;
            text-decoration: none;
            font-weight: bold;
            border-radius: 4px;
            margin: 20px 0;
        }
        .btn-recovery:hover {
            background-color: #45a049;
        }
        .coupon-code {
            background-color: #f8f9fa;
            border: 1px dashed #dddddd;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        .coupon-label {
            font-size: 14px;
            margin: 0;
        }
        .coupon-value {
            font-size: 20px;
            font-weight: bold;
            margin: 10px 0;
            letter-spacing: 1px;
            color: #333333;
        }
        .footer {
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #999999;
            border-top: 1px solid #eeeeee;
        }
        .unsubscribe {
            font-size: 11px;
            color: #999999;
            margin-top: 10px;
        }
        @media only screen and (max-width: 600px) {
            .container {
                width: 100%;
                border-radius: 0;
            }
            .content {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h2>
            <p>Your cart is waiting for you!</p>
        </div>
        
        <div class="content">
            <p>Hello<?php echo !empty( $customer_name ) ? ' ' . esc_html( $customer_name ) : ''; ?>,</p>
            
            <p>We noticed that you left some items in your shopping cart at <?php echo esc_html( get_bloginfo( 'name' ) ); ?>.</p>
            
            <p>Your cart is still saved, and you can complete your purchase by clicking the button below:</p>
            
            <?php if ( !empty( $cart_items ) ) : ?>
                <h3>Your Cart Items:</h3>
                <?php echo wp_kses_post( $cart_items ); ?>
            <?php endif; ?>
            
            <div style="text-align: center;">
                <a href="<?php echo esc_url( $recovery_link ); ?>" class="btn-recovery"><?php echo esc_html( $button_text ); ?></a>
            </div>
            
            <?php if ( !empty( $coupon_code ) ) : ?>
                <div class="coupon-code">
                    <p class="coupon-label"><?php echo esc_html__( 'Use this coupon code for a special discount:', 'shopmetrics' ); ?></p>
                    <p class="coupon-value"><?php echo esc_html( $coupon_code ); ?></p>
                </div>
            <?php endif; ?>
            
            <p>Thank you for shopping with us!</p>
            
            <p>Regards,<br>
            <?php echo esc_html( get_bloginfo( 'name' ) ); ?> Team</p>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?>. All rights reserved.</p>
            <p class="unsubscribe">This email was sent to you because you left items in your shopping cart. 
            If you don't want to receive these emails in the future, please <a href="<?php echo esc_url( home_url( '/contact' ) ); ?>">contact us</a>.</p>
        </div>
    </div>
</body>
</html> 