<?php
/**
 * Class ShopMetrics_Snapshotter
 *
 * Handles the periodic collection of inventory snapshots.
 */
class ShopMetrics_Snapshotter {

    /**
     * The hook name for the scheduled action.
     */
    const SNAPSHOT_HOOK = 'shopmetrics_analytics_take_inventory_snapshot';

    /**
     * Initialize the snapshotter, scheduling the action if it's not already scheduled.
     */
    public static function init() {
        add_action( 'plugins_loaded', array( __CLASS__, 'schedule_snapshot_action' ) );
        add_action( self::SNAPSHOT_HOOK, array( __CLASS__, 'take_inventory_snapshot' ) );
    }

    /**
     * Schedule the inventory snapshot action if not already scheduled.
     * Uses Action Scheduler.
     */
    public static function schedule_snapshot_action() {
        if ( ! class_exists( 'ActionScheduler_Store' ) ) {
            // Action Scheduler is not active, or not loaded yet. Cannot proceed.
            // Optionally, add an admin notice here.
            return;
        }

        if ( false === as_next_scheduled_action( self::SNAPSHOT_HOOK ) ) {
            // Schedule to run daily, around a specific time (e.g., 2 AM site time).
            // The exact time can be adjusted based on expected site traffic.
            as_schedule_recurring_action( strtotime( 'tomorrow 2am' ), DAY_IN_SECONDS, self::SNAPSHOT_HOOK, array(), 'ShopMetrics' );
        }
    }

    /**
     * The main function to take an inventory snapshot.
     * This function is executed by the Action Scheduler.
     */
    public static function take_inventory_snapshot() {
        // Allow if called by Action Scheduler, WP Cron, or our defined manual trigger constant.
        if ( ! did_action( 'action_scheduler_run_queue' ) && ! wp_doing_cron() && ( ! defined( 'SM_DOING_MANUAL_SNAPSHOT' ) || ! SM_DOING_MANUAL_SNAPSHOT ) ) {
            \ShopMetrics_Logger::get_instance()->warn("take_inventory_snapshot called outside of an allowed context (AS, Cron, or Manual Trigger).");
            return;
        }
        
        $site_identifier = get_option('shopmetrics_analytics_site_identifier');
        $api_token = get_option('shopmetrics_analytics_api_token');
        
        // Ensure API URL constant is defined
        if (!defined('SHOPMETRICS_API_URL')) {
            \ShopMetrics_Logger::get_instance()->error("SHOPMETRICS_API_URL constant is not defined. Cannot determine API endpoint. Skipping snapshot.");
            return;
        }
        $api_url = SHOPMETRICS_API_URL;

        if ( empty($site_identifier) || empty($api_token) || empty($api_url) ) {
            \ShopMetrics_Logger::get_instance()->error("Site Identifier, API Token, or API URL not configured. Skipping snapshot.");
            return;
        }

        // Ensure plugin version constant is defined
        if (!defined('SHOPMETRICS_VERSION')) {
            \ShopMetrics_Logger::get_instance()->warn("SHOPMETRICS_VERSION constant is not defined. Plugin version will be missing.");
            // We can proceed but log the missing version, or return if it's critical.
            // For now, let's proceed and use a placeholder or allow it to be missing.
            // define('SHOPMETRICS_VERSION', '0.0.0-undefined'); // Temporary fallback if you want to send something
        }
        $plugin_version = defined('SHOPMETRICS_VERSION') ? SHOPMETRICS_VERSION : 'unknown';

        \ShopMetrics_Logger::get_instance()->info("Starting inventory snapshot...");

        // 1. Get Global Low Stock Threshold from WooCommerce settings
        $low_stock_threshold = get_option('woocommerce_notify_low_stock_amount');
        if ($low_stock_threshold === false || $low_stock_threshold === '') {
            // Handle case where threshold might not be set, default to a sensible value or skip if critical
            $low_stock_threshold = 0; // Or perhaps log an error and return if this is essential.
            \ShopMetrics_Logger::get_instance()->info("WooCommerce Low Stock Threshold not explicitly set, defaulting to 0.");
        }

        // 2. Query all relevant products
        //    - Focus on stock-managed items
        //    - Consider pagination for very large stores to avoid timeouts
        $products_data = array();
        $page = 1;
        $products_per_page = 100; // Adjust as needed

        do {
            $args = array(
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => $products_per_page,
                'paged'          => $page,
                'meta_query'     => array(
                    array(
                        'key'     => '_manage_stock',
                        'value'   => 'yes', 
                        'compare' => '=',
                    ),
                ),
                // Potentially add more specific queries if needed, e.g., exclude certain product types
            );
            $query = new WP_Query( $args );

            if ( $query->have_posts() ) {
                while ( $query->have_posts() ) {
                    $query->the_post();
                    $product_id = get_the_ID();
                    $product = wc_get_product( $product_id );

                    if ( ! $product ) {
                        continue;
                    }

                    // Skip non-stock managed products if the query didn't perfectly filter (shouldn't happen with _manage_stock = yes)
                    if ( ! $product->managing_stock() ) {
                        continue;
                    }

                    // 3. For each product, extract required data
                    $product_item = array(
                        'id'             => $product->get_id(),
                        'name'           => $product->get_name(),
                        'sku'            => $product->get_sku(),
                        'stock_status'   => $product->get_stock_status(), // e.g., 'instock', 'outofstock', 'onbackorder'
                        'stock_quantity' => $product->get_stock_quantity(),
                        'price'          => $product->get_price(),
                        'categories'     => wc_get_product_category_list( $product->get_id(), ', ', '', '' ), // Returns comma-separated string
                        'cogs'           => self::get_product_cogs( $product ),
                    );
                    
                    // Handle variations if any
                    if ($product->is_type('variable')) {
                        $variations_data = array();
                        foreach ($product->get_children() as $variation_id) {
                            $variation = wc_get_product($variation_id);
                            if ($variation && $variation->exists() && $variation->managing_stock()) {
                                // Fetch and format attributes
                                $attributes_array = array();
                                $wc_attributes = $variation->get_attributes();
                                if ( ! empty( $wc_attributes ) ) {
                                    foreach ( $wc_attributes as $attribute_slug => $attribute_term_slug_or_value ) {
                                        // For global attributes (taxonomies), get the term name
                                        // For local custom attributes, the value is directly available.
                                        $attribute_name = wc_attribute_label( $attribute_slug, $product ); // Get the display name of the attribute
                                        $attribute_value = $variation->get_attribute( $attribute_slug ); // This gets the term name or custom value
                                        
                                        if ( ! empty( $attribute_value ) ) {
                                            // Use a sanitized/simplified name for the attribute key if desired, or keep original
                                            $attributes_array[sanitize_title($attribute_name)] = $attribute_value;
                                        } else {
                                            // Fallback for cases where get_attribute might return empty for some reason
                                            // but the attribute key exists. This is less common.
                                            $term = get_term_by('slug', $attribute_term_slug_or_value, $attribute_slug);
                                            if ($term && !is_wp_error($term)) {
                                                $attributes_array[sanitize_title($attribute_name)] = $term->name;
                                            }
                                        }
                                    }
                                }

                                $variations_data[] = array(
                                    'id'             => $variation->get_id(),
                                    'name'           => $variation->get_name(), // Or $variation->get_formatted_name()
                                    'sku'            => $variation->get_sku(),
                                    'stock_status'   => $variation->get_stock_status(),
                                    'stock_quantity' => $variation->get_stock_quantity(),
                                    'price'          => $variation->get_price(),
                                    'cogs'           => self::get_product_cogs( $variation ),
                                    'attributes'     => $attributes_array, // Add formatted attributes here
                                );
                            }
                        }
                        if (!empty($variations_data)) {
                            $product_item['variations'] = $variations_data;
                        }
                    } 
                    // Only add product if it's a simple product OR a variable product with stock-managed variations.
                    // If it's variable and has no stock-managed variations, we might skip it or handle as parent only.
                    // For now, we add the parent, and if variations exist, they are nested.

                    $products_data[] = $product_item;
                }
            } else {
                // No more posts found, break the loop.
                break;
            }
            wp_reset_postdata();
            $page++;
        } while ( $query->max_num_pages >= $page );


        // 4. Package data into JSON structure
        $snapshot_payload = array(
            'site_identifier'       => $site_identifier,
            'snapshot_timestamp'    => current_time( 'mysql', true ), // GMT timestamp
            'global_low_stock_threshold' => (int) $low_stock_threshold,
            'products'              => $products_data,
            'plugin_version'        => $plugin_version
        );

        // Ensure /v1/ is present in the endpoint
        $api_url_with_v1 = rtrim($api_url, '/');
        if (!preg_match('#/v[0-9]+$#', $api_url_with_v1)) {
            $api_url_with_v1 .= '/v1';
        }
        $endpoint = trailingslashit($api_url_with_v1) . 'inventory-snapshot';

        \ShopMetrics_Logger::get_instance()->debug("Sending snapshot to endpoint: " . $endpoint);

        $response = wp_remote_post( $endpoint, array(
            'method'    => 'POST',
            'timeout'   => 45, // Seconds
            'headers'   => array(
                'Content-Type' => 'application/json; charset=utf-8',
                'X-FinanciarMe-Token' => $api_token,
                'X-FinanciarMe-Site-Identifier' => $site_identifier, // Also send site_identifier in headers as good practice
            ),
            'body'      => wp_json_encode( $snapshot_payload ),
            'data_format' => 'body',
        ));

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            \ShopMetrics_Logger::get_instance()->error("Error sending inventory snapshot: $error_message");
        } else {
            $response_code = wp_remote_retrieve_response_code( $response );
            $response_body = wp_remote_retrieve_body( $response );
            if ( $response_code >= 200 && $response_code < 300 ) {
                \ShopMetrics_Logger::get_instance()->info("Inventory snapshot sent successfully. Response code: $response_code");
            } else {
                \ShopMetrics_Logger::get_instance()->error("Failed to send inventory snapshot. Response code: $response_code. Body: $response_body");
            }
        }

        // --- Send Low Stock Email Notifications ---
        $settings = get_option('shopmetrics_settings', []);
        $enable_notifications = !empty($settings['enable_low_stock_notifications']);
        if ($enable_notifications) {
            \ShopMetrics_Logger::get_instance()->info("Low stock email notifications are enabled. Checking for low stock products...");
            
            // Check if notifications were recently sent (within 24 hours)
            $last_notification_time = get_option('shopmetrics_analytics_last_low_stock_notification', 0);
            $notification_interval = 86400; // 24 hours in seconds

            if (time() - $last_notification_time < $notification_interval) {
                \ShopMetrics_Logger::get_instance()->info("Skipping low stock notifications as they were already sent within the last 24 hours. Last sent: " . gmdate('Y-m-d H:i:s', $last_notification_time));
            } else {
                $recipient_setting = $settings['low_stock_notification_recipients'] ?? '';
                $recipients = array();
                if (empty(trim($recipient_setting))) {
                    $recipients[] = get_option('admin_email');
                    \ShopMetrics_Logger::get_instance()->debug("No specific recipients set for low stock emails, using admin email: " . get_option('admin_email'));
                } else {
                    $recipients = array_map('trim', explode(',', $recipient_setting));
                    // Filter out any non-valid emails that might have slipped past sanitization
                    $recipients = array_filter($recipients, 'is_email');
                    \ShopMetrics_Logger::get_instance()->debug("Low stock email recipients: " . implode(', ', $recipients));
                }

                if (empty($recipients)) {
                    \ShopMetrics_Logger::get_instance()->warn("No valid recipients found for low stock emails. Skipping email.");
                } else {
                    $low_stock_products = array();
                    // Ensure $low_stock_threshold is numeric before comparison; default to 0 if not.
                    $numeric_low_stock_threshold = is_numeric($low_stock_threshold) ? (int) $low_stock_threshold : 0;

                    foreach ($products_data as $p_data) {
                        // Check simple product stock
                        if (isset($p_data['stock_quantity']) && is_numeric($p_data['stock_quantity'])) {
                            // Only consider products that are 'instock' or 'onbackorder' but not 'outofstock'
                            if ($p_data['stock_status'] !== 'outofstock' && $p_data['stock_quantity'] <= $numeric_low_stock_threshold) {
                                $low_stock_products[] = $p_data;
                            }
                        }

                        // Check variation stock
                        if (isset($p_data['variations']) && is_array($p_data['variations'])) {
                            foreach ($p_data['variations'] as $var_data) {
                                if (isset($var_data['stock_quantity']) && is_numeric($var_data['stock_quantity'])) {
                                    // Only consider variations that are 'instock' or 'onbackorder' but not 'outofstock'
                                    if ($var_data['stock_status'] !== 'outofstock' && $var_data['stock_quantity'] <= $numeric_low_stock_threshold) {
                                        // Add parent name for context if variation name is just attributes
                                        $var_data['parent_name'] = $p_data['name'];
                                        $low_stock_products[] = $var_data;
                                    }
                                }
                            }
                        }
                    }

                    if (!empty($low_stock_products)) {
                        \ShopMetrics_Logger::get_instance()->info("Found " . count($low_stock_products) . " product(s)/variation(s) at or below low stock threshold. Preparing email.");

                        $site_name = get_bloginfo('name');
                        // translators: %s is the site name
                        $subject = sprintf(__('Low Stock Product Alert - %s', 'shopmetrics'), $site_name);
                        
                        $email_body = '<p>' . __('The following products/variations are currently at or below their low stock threshold:', 'shopmetrics') . '</p>';
                        $email_body .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
                        $email_body .= '<thead><tr>';
                        $email_body .= '<th>' . __('Product Name', 'shopmetrics') . '</th>';
                        $email_body .= '<th>' . __('SKU', 'shopmetrics') . '</th>';
                        $email_body .= '<th>' . __('Current Stock', 'shopmetrics') . '</th>';
                        $email_body .= '<th>' . __('Low Stock Threshold', 'shopmetrics') . '</th>';
                        $email_body .= '</tr></thead><tbody>';

                        foreach ($low_stock_products as $lsp) {
                            $product_name_display = isset($lsp['parent_name']) ? $lsp['parent_name'] . ' - ' . $lsp['name'] : $lsp['name'];
                            $email_body .= '<tr>';
                            $email_body .= '<td>' . esc_html($product_name_display) . '</td>';
                            $email_body .= '<td>' . esc_html($lsp['sku'] ?? __('N/A', 'shopmetrics')) . '</td>';
                            $email_body .= '<td>' . esc_html($lsp['stock_quantity']) . '</td>';
                            $email_body .= '<td>' . esc_html($numeric_low_stock_threshold) . '</td>';
                            $email_body .= '</tr>';
                        }

                        $email_body .= '</tbody></table>';
                        // translators: %s is the site name
                        $email_body .= '<p>' . sprintf(__('This is an automated notification from the ShopMetrics plugin on %s.', 'shopmetrics'), esc_html($site_name)) . '</p>';

                        $headers = array('Content-Type: text/html; charset=UTF-8');

                        if (wp_mail($recipients, $subject, $email_body, $headers)) {
                            \ShopMetrics_Logger::get_instance()->info("Low stock email successfully sent to: " . implode(', ', $recipients));
                            // Update the timestamp after successfully sending the email
                            update_option('shopmetrics_analytics_last_low_stock_notification', time());
                        } else {
                            \ShopMetrics_Logger::get_instance()->error("Failed to send low stock email to: " . implode(', ', $recipients));
                            // Additional logging for wp_mail failure if WP_DEBUG is on
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                global $ts_mail_errors; // WordPress tracks mail errors in this global if a plugin (like WP Mail Logging) is used
                                global $phpmailer;     // PHPMailer instance is often in this global

                                if (!empty($ts_mail_errors) && is_array($ts_mail_errors)) { // Check if array and not empty
                                    \ShopMetrics_Logger::get_instance()->debug("wp_mail sending errors: " . wp_json_encode($ts_mail_errors));
                                }
                                if (isset($phpmailer) && is_object($phpmailer) && !empty($phpmailer->ErrorInfo)) {
                                     \ShopMetrics_Logger::get_instance()->debug("PHPMailer error info: " . $phpmailer->ErrorInfo);
                                }
                            }
                        }
                    } else {
                        \ShopMetrics_Logger::get_instance()->debug("No products found at or below low stock threshold. No email sent.");
                    }
                }
            }
        } else {
            \ShopMetrics_Logger::get_instance()->debug("Low stock email notifications are disabled.");
        }
        // --- End Send Low Stock Email Notifications ---        

        \ShopMetrics_Logger::get_instance()->info("Inventory snapshot finished. Products processed: " . count($products_data));
        // For debugging, you might want to log the payload (careful with large data):
        // \ShopMetrics_Logger::get_instance()->debug("Snapshot payload: " . print_r($snapshot_payload, true));
    }

    /**
     * Helper function to get COGS for a product.
     * This needs to be adapted based on how COGS is stored (e.g., meta key from plugin settings).
     *
     * @param WC_Product $product The WooCommerce product object.
     * @return float|null The cost of goods sold, or null if not found.
     */
    private static function get_product_cogs( $product ) {
        $settings = get_option('shopmetrics_settings', []);
        $cogs_meta_key = $settings['cogs_meta_key'] ?? '';
        $cogs_default_percentage = $settings['cogs_default_percentage'] ?? null;
        $cogs_value = null;

        // 1. Try to get COGS from meta key
        if ( ! empty( $cogs_meta_key ) ) {
            $meta_value = $product->get_meta( $cogs_meta_key, true );
            if ( $meta_value !== '' && is_numeric( $meta_value ) ) { // Check if meta_value is not an empty string and is numeric
                $cogs_value = (float) $meta_value;
            }
        }

        // 2. If COGS not found via meta key, try default percentage
        if ( is_null( $cogs_value ) && ! is_null( $cogs_default_percentage ) && is_numeric( $cogs_default_percentage ) ) {
            $price = $product->get_price();
            if ( $price !== '' && is_numeric( $price ) ) { // Ensure price is valid
                $cogs_value = ( (float) $cogs_default_percentage / 100 ) * (float) $price;
            }
        }
        
        return !is_null($cogs_value) ? round( (float) $cogs_value, wc_get_price_decimals() ) : null;
    }
}

ShopMetrics_Snapshotter::init(); 