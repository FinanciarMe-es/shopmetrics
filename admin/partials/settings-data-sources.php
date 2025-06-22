<?php
/**
 * Data Sources settings page template.
 *
 * @link       https://shopmetrics.es
 * @since      1.3.0
 *
 * @package    ShopMetrics
 * @subpackage ShopMetrics/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap">
    <div class="sm-settings-section">
        <h2 style="display: flex; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
            <img src="<?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'admin/images/woo-logo.svg'); ?>" 
                 alt="WooCommerce" style="height: 24px; width: auto; margin-right: 10px;">
            <?php esc_html_e('WooCommerce Integration', 'shopmetrics'); ?>
        </h2>
        
        <p class="sm-settings-description">
            <?php esc_html_e('Configure how your WooCommerce store integrates with ShopMetrics.', 'shopmetrics'); ?>
        </p>
        
        <!-- Inventory Synchronization Section -->
        <div class="sm-integration-block">
            <div class="sm-integration-block-header">
                <span class="dashicons dashicons-database"></span>
                <h3><?php esc_html_e('Inventory Synchronization', 'shopmetrics'); ?></h3>
            </div>
            <p><?php esc_html_e('Configure how inventory data is synchronized with ShopMetrics.', 'shopmetrics'); ?></p>
            
            <div class="sm-subsection">
                <h4><?php esc_html_e('Scheduled Snapshots', 'shopmetrics'); ?></h4>
                <p><?php esc_html_e('Inventory snapshots are scheduled to run automatically once per day. This keeps your stock data up to date in the dashboard.', 'shopmetrics'); ?></p>
                <?php 
                // Check if Action Scheduler is active
                if (class_exists('ActionScheduler_Store')) {
                    $next_snapshot = as_next_scheduled_action('shopmetrics_analytics_take_inventory_snapshot');
                    if ($next_snapshot) {
                        echo '<p><strong>' . esc_html__('Next scheduled snapshot:', 'shopmetrics') . '</strong> ' . 
                             esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_snapshot)) . '</p>';
                    } else {
                        echo '<p class="sm-error-text">' . esc_html__('No snapshot is currently scheduled. This may indicate an issue with your WordPress scheduled tasks.', 'shopmetrics') . '</p>';
                        echo '<p><a href="#" id="fix-snapshot-schedule" class="button" data-nonce="' . esc_attr(wp_create_nonce('sm_settings_ajax_nonce')) . '">' . esc_html__('Fix Schedule', 'shopmetrics') . '</a></p>';
                    }
                } else {
                    echo '<p class="sm-error-text">' . esc_html__('Action Scheduler is not active. This plugin is required for WooCommerce and should be active. Please check your WordPress installation.', 'shopmetrics') . '</p>';
                }
                ?>
            </div>
            
            <div class="sm-subsection">
                <h4><?php esc_html_e('Manual Inventory Snapshot', 'shopmetrics'); ?></h4>
                <p><?php esc_html_e('If your stock data is not updating automatically, you can trigger a manual snapshot here. This will collect current inventory data from your store and send it to ShopMetrics.', 'shopmetrics'); ?></p>
                
                <button id="manual-snapshot-trigger" class="button button-primary" data-nonce="<?php echo esc_attr(wp_create_nonce('sm_settings_ajax_nonce')); ?>">
                    <?php esc_html_e('Take Inventory Snapshot Now', 'shopmetrics'); ?>
                </button>
                <span id="manual-snapshot-status" style="display:none; margin-left: 10px;"></span>
            </div>
        </div>
        
        <!-- Stock Synchronization Section -->
        <div class="sm-integration-block">
            <div class="sm-integration-block-header">
                <span class="dashicons dashicons-chart-line"></span>
                <h3><?php esc_html_e('Stock Monitoring', 'shopmetrics'); ?></h3>
            </div>
            <p><?php esc_html_e('Configure settings related to stock monitoring and tracking.', 'shopmetrics'); ?></p>
            
            <div class="sm-subsection">
                <h4><?php esc_html_e('WooCommerce Stock Settings', 'shopmetrics'); ?></h4>
                <?php
                // Get WooCommerce stock settings
                $wc_manage_stock = get_option('woocommerce_manage_stock');
                $wc_notify_low_stock = get_option('woocommerce_notify_low_stock_amount');
                $wc_notify_no_stock = get_option('woocommerce_notify_no_stock_amount');
                ?>
                <p><?php esc_html_e('Your current WooCommerce stock settings are displayed below. These settings affect how ShopMetrics identifies low stock items.', 'shopmetrics'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Stock Management', 'shopmetrics'); ?></th>
                        <td>
                            <?php if ($wc_manage_stock === 'yes'): ?>
                                <span class="dashicons dashicons-yes" style="color: green;"></span> 
                                <?php esc_html_e('Enabled', 'shopmetrics'); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-no" style="color: red;"></span> 
                                <?php esc_html_e('Disabled', 'shopmetrics'); ?>
                            <?php endif; ?>
                            <p class="description">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=products&section=inventory')); ?>" target="_blank">
                                    <?php esc_html_e('Manage in WooCommerce Settings →', 'shopmetrics'); ?>
                                </a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Low Stock Threshold', 'shopmetrics'); ?></th>
                        <td>
                            <?php echo esc_html($wc_notify_low_stock); ?>
                            <p class="description">
                                <?php esc_html_e('When product stock reaches this amount, ShopMetrics will mark it as Low Stock.', 'shopmetrics'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Out of Stock Threshold', 'shopmetrics'); ?></th>
                        <td>
                            <?php echo esc_html($wc_notify_no_stock); ?>
                            <p class="description">
                                <?php esc_html_e('When product stock reaches this amount, WooCommerce considers it out of stock.', 'shopmetrics'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <form method="post" action="options.php" id="sm-data-sources-unified-form">
            <div class="sm-subsection">
                <h4><?php esc_html_e('Low Stock Notifications', 'shopmetrics'); ?></h4>
                <p><?php esc_html_e('Configure email notifications for products that reach their low stock threshold.', 'shopmetrics'); ?></p>
                
                <?php settings_fields('shopmetrics_settings_group'); ?>
                <?php 
                // Multiple fallback approaches to get the admin instance
                if (isset($GLOBALS['shopmetricsanalytics_admin']) && is_object($GLOBALS['shopmetricsanalytics_admin'])) {
                    // First try: Use global if available
                    $admin = $GLOBALS['shopmetricsanalytics_admin'];
                    if (method_exists($admin, 'render_data_sources_cogs_fields')) {
                        $admin->render_data_sources_cogs_fields();
                    }
                } elseif (class_exists('ShopMetrics\\Analytics\\ShopMetrics')) {
                    // Second try: Get from singleton
                    $plugin = FinanciarMe\Analytics\ShopMetrics::get_instance();
                    if (isset($plugin->admin) && method_exists($plugin->admin, 'render_data_sources_cogs_fields')) {
                        $plugin->admin->render_data_sources_cogs_fields();
                    }
                } else {
                    // Final fallback: Just output hidden fields directly
                    $api_token = get_option('shopmetrics_analytics_api_token', '');
                    $site_identifier = get_option('shopmetrics_analytics_site_identifier', '');
                    echo '<input type="hidden" name="shopmetrics_settings[api_token]" value="' . esc_attr($api_token) . '" />';
                    echo '<input type="hidden" name="shopmetrics_settings[site_identifier]" value="' . esc_attr($site_identifier) . '" />';
                }
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Low Stock Notifications', 'shopmetrics'); ?></th>
                        <td>
                            <?php 
                            $settings = get_option('shopmetrics_settings', []);
                            ?>
                            <input type="hidden" name="shopmetrics_settings[enable_low_stock_notifications]" value="0" />
                            <input type="checkbox" name="shopmetrics_settings[enable_low_stock_notifications]" value="1" <?php checked(!empty($settings['enable_low_stock_notifications'])); ?> />
                            <p class="description">
                                <?php esc_html_e('Check this box to enable daily email notifications for products that have reached their low stock threshold.', 'shopmetrics'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Notification Recipients', 'shopmetrics'); ?></th>
                        <td>
                            <?php
                            $recipients = get_option('shopmetrics_analytics_low_stock_notification_recipients', '');
                            ?>
                            <input type="text" class="regular-text" name="shopmetrics_settings[low_stock_notification_recipients]" value="<?php echo esc_attr($settings['low_stock_notification_recipients'] ?? ''); ?>" placeholder="<?php esc_attr_e('e.g., admin@example.com, manager@example.com', 'shopmetrics'); ?>" />
                            <p class="description">
                                <?php esc_html_e('Enter one or more email addresses, separated by commas. If empty, notifications will be sent to the site administrator\'s email.', 'shopmetrics'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Visit Tracking Settings -->
        <div class="sm-integration-block">
            <div class="sm-integration-block-header">
                <span class="dashicons dashicons-chart-bar"></span>
                <h3><?php esc_html_e('Visit Tracking', 'shopmetrics'); ?></h3>
            </div>
            <p><?php esc_html_e('Configure how customer visits and website activity are tracked by FinanciarMe Analytics.', 'shopmetrics'); ?></p>

            <div class="sm-subsection">
                <h4><?php esc_html_e('Tracking Settings', 'shopmetrics'); ?></h4>
                <p><?php esc_html_e('Control how site visits are tracked and what data is collected.', 'shopmetrics'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Visit Tracking', 'shopmetrics'); ?></th>
                        <td>
                            <input type="hidden" name="shopmetrics_settings[enable_visit_tracking]" value="0" />
                            <input type="checkbox" name="shopmetrics_settings[enable_visit_tracking]" value="1" <?php checked(!empty($settings['enable_visit_tracking'])); ?> />
                            <p class="description">
                                <?php esc_html_e('When enabled, the plugin will track information about site visits, including pages viewed, referrers, and UTM parameters. This data powers marketing analytics in the dashboard.', 'shopmetrics'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h4><?php esc_html_e('Collected Data', 'shopmetrics'); ?></h4>
                <p><?php esc_html_e('The following information is collected when visit tracking is enabled:', 'shopmetrics'); ?></p>
                <ul class="sm-list">
                    <li><strong><?php esc_html_e('Page URLs:', 'shopmetrics'); ?></strong> <?php esc_html_e('The addresses of pages that visitors view on your site', 'shopmetrics'); ?></li>
                    <li><strong><?php esc_html_e('Page Types:', 'shopmetrics'); ?></strong> <?php esc_html_e('Categories of pages (e.g., product, cart, checkout)', 'shopmetrics'); ?></li>
                    <li><strong><?php esc_html_e('Referrers:', 'shopmetrics'); ?></strong> <?php esc_html_e('Where visitors came from before landing on your site', 'shopmetrics'); ?></li>
                    <li><strong><?php esc_html_e('UTM Parameters:', 'shopmetrics'); ?></strong> <?php esc_html_e('Marketing campaign tracking parameters', 'shopmetrics'); ?></li>
                </ul>
            </div>
        </div>
        
        <!-- Cost of Goods Settings section -->
        <div class="sm-integration-block">
            <div class="sm-integration-block-header">
                <span class="dashicons dashicons-money-alt"></span>
                <h3><?php esc_html_e('Cost of Goods Settings', 'shopmetrics'); ?></h3>
            </div>
            <p><?php esc_html_e('Configure Cost of Goods Sold (COGS) tracking to enable accurate profit calculations for your products.', 'shopmetrics'); ?></p>
            
            <div class="sm-subsection">
                <h4><?php esc_html_e('COGS Meta Key Detection', 'shopmetrics'); ?></h4>
                <p><?php esc_html_e('The plugin needs to know which WooCommerce meta key stores your product cost information.', 'shopmetrics'); ?></p>
                
                <div id="sm_cogs_meta_key_setting_area">
                    <p>
                        <strong><?php esc_html_e('Currently Saved Key:', 'shopmetrics'); ?></strong>
                        <?php 
                        $settings = get_option('shopmetrics_settings', []);
                        ?>
                        <code id="sm_current_cogs_key_display"><?php echo esc_html($settings['cogs_meta_key'] ?? __('Not set', 'shopmetrics')); ?></code>
                    </p>

                    <button type="button" id="sm_auto_detect_cogs_key_button" class="button">
                        <?php esc_html_e('Auto-detect COGS Meta Key', 'shopmetrics'); ?>
                    </button>
                    <button type="button" id="sm_manual_select_cogs_key_button" class="button">
                        <?php esc_html_e('Select Key Manually', 'shopmetrics'); ?>
                    </button>
                    
                    <div id="sm_cogs_detection_result_area" style="margin-top: 10px; padding: 10px; border: 1px solid #eee; display: none;">
                        <!-- JS will populate this -->
                    </div>

                    <div id="sm_cogs_manual_select_area" style="margin-top: 10px; display: none;">
                        <select id="sm_cogs_meta_key_dropdown" name="shopmetrics_settings[cogs_meta_key_dropdown_select]" style="min-width: 200px;">
                            <option value=""><?php esc_html_e('-- Select a Key --', 'shopmetrics'); ?></option>
                            <option value=""><?php esc_html_e('-- Do not use a meta key --', 'shopmetrics'); ?></option> 
                            <!-- JS will populate this -->
                        </select>
                        <p class="description"><?php esc_html_e('Select the meta key for COGS. If your key is not listed, ensure orders with that meta key exist.', 'shopmetrics'); ?></p>
                    </div>

                    <!-- Hidden input that actually saves the value -->
                    <input 
                        type="hidden" 
                        id="shopmetrics_settings_cogs_meta_key_hidden_input" 
                        name="shopmetrics_settings[cogs_meta_key]" 
                        value="<?php echo esc_attr($settings['cogs_meta_key'] ?? ''); ?>" 
                    />

                    <p class="description" style="margin-top:15px;">
                        <?php esc_html_e('The plugin will attempt to find the Cost of Goods Sold (COGS) for each order item using this meta key.', 'shopmetrics'); ?>
                        <br>
                        <?php printf(
                            /* translators: %s: Example meta key */
                            esc_html__('Common example: %s. This is used by plugins like WooCommerce Cost of Goods.', 'shopmetrics'),
                            '<code>_wc_cog_item_cost</code>'
                        ); ?>
                    </p>
                </div>
            </div>
            
            <div class="sm-subsection">
                <h4><?php esc_html_e('Default COGS Percentage', 'shopmetrics'); ?></h4>
                <p><?php esc_html_e('If per-item COGS is not found via the meta key, you can specify a default percentage to estimate costs.', 'shopmetrics'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Default Percentage', 'shopmetrics'); ?></th>
                        <td>
                            <?php
                            $cogs_percentage = get_option('shopmetrics_analytics_cogs_default_percentage', '');
                            ?>
                            <input 
                                type="number" 
                                step="1" 
                                min="0" 
                                max="100" 
                                id="shopmetrics_settings_cogs_default_percentage" 
                                name="shopmetrics_settings[cogs_default_percentage]" 
                                value="<?php echo esc_attr($settings['cogs_default_percentage'] ?? ''); ?>" 
                                class="regular-text"
                                placeholder="<?php esc_attr_e('e.g., 60 for 60%', 'shopmetrics'); ?>"
                            /> %
                            <p class="description">
                                <?php esc_html_e('If per-item COGS is not found via the meta key, use this percentage of the item\'s pre-discount subtotal to estimate COGS (0-100).', 'shopmetrics'); ?>
                                <br>
                                <?php esc_html_e('Leave blank if you only use meta key COGS or do not want to estimate.', 'shopmetrics'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php submit_button(__('Save All Settings', 'shopmetrics')); ?>
    </div>
    </form>

    <div class="sm-settings-section sm-future-integration" data-integration="ads-platforms" style="display: none !important;">
        <h2 style="display: flex; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;" data-integration="ads-platforms">
            <span class="dashicons dashicons-megaphone" style="font-size: 24px; width: 24px; height: 24px; margin-right: 10px; color: #2271b1;"></span>
            <?php esc_html_e('Ads Platforms Integration', 'shopmetrics'); ?>
        </h2>
        
        <p class="sm-settings-description">
            <?php esc_html_e('Connect your advertising platforms to display correlation between campaign expenses and profit. This helps you understand which campaigns are most profitable for your business.', 'shopmetrics'); ?>
        </p>
        
        <div class="sm-integration-grid">
            <!-- Google Analytics -->
            <div class="sm-integration-card">
                <div class="sm-integration-logo">
                    <img src="<?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'admin/images/google-analytics-logo.svg'); ?>" 
                         onerror="this.src='<?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'admin/images/google-analytics-logo.png'); ?>'; 
                                     if (this.naturalWidth === 0) this.src='<?php echo esc_url(admin_url('images/wordpress-logo.svg')); ?>';" 
                         alt="Google Analytics">
                </div>
                <div class="sm-integration-content">
                    <h4><?php esc_html_e('Google Analytics', 'shopmetrics'); ?></h4>
                    <p><?php esc_html_e('Aggregate all advertising platform data in one place for comprehensive analytics.', 'shopmetrics'); ?></p>
                    <button class="button sm-connect-button" data-service="google-analytics" data-nonce="<?php echo esc_attr(wp_create_nonce('sm_connect_service_nonce')); ?>">
                        <?php esc_html_e('Connect', 'shopmetrics'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Google Ads -->
            <div class="sm-integration-card">
                <div class="sm-integration-logo">
                    <img src="<?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'admin/images/google-ads-logo.png'); ?>" 
                         onerror="this.src='<?php echo esc_url(admin_url('images/wordpress-logo.svg')); ?>';" 
                         alt="Google Ads">
                </div>
                <div class="sm-integration-content">
                    <h4><?php esc_html_e('Google Ads', 'shopmetrics'); ?></h4>
                    <p><?php esc_html_e('Track expenses and performance of your Google Ads campaigns.', 'shopmetrics'); ?></p>
                    <button class="button sm-connect-button" data-service="google-ads" data-nonce="<?php echo esc_attr(wp_create_nonce('sm_connect_service_nonce')); ?>">
                        <?php esc_html_e('Connect', 'shopmetrics'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Meta Ads -->
            <div class="sm-integration-card">
                <div class="sm-integration-logo">
                    <img src="<?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'admin/images/meta-ads-logo.svg'); ?>"
                         onerror="this.src='<?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'admin/images/meta-ads-logo.png'); ?>';
                                     if (this.naturalWidth === 0) this.src='<?php echo esc_url(admin_url('images/wordpress-logo.svg')); ?>';"
                         alt="Meta Ads">
                </div>
                <div class="sm-integration-content">
                    <h4><?php esc_html_e('Meta Ads', 'shopmetrics'); ?></h4>
                    <p><?php esc_html_e('Track expenses and performance of your Facebook and Instagram ad campaigns.', 'shopmetrics'); ?></p>
                    <button class="button sm-connect-button" data-service="meta-ads" data-nonce="<?php echo esc_attr(wp_create_nonce('sm_connect_service_nonce')); ?>">
                        <?php esc_html_e('Connect', 'shopmetrics'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="sm-settings-section sm-future-integration" data-integration="accounting-platforms">
        <h2 style="display: flex; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;" data-integration="accounting-platforms">
            <span class="dashicons dashicons-chart-area" style="font-size: 24px; width: 24px; height: 24px; margin-right: 10px; color: #2271b1;"></span>
            <?php esc_html_e('Accounting Platforms Integration', 'shopmetrics'); ?>
        </h2>
        
        <p class="sm-settings-description">
            <?php esc_html_e('Connect your accounting software to calculate the overall profitability of your business, including not only advertising expenses but also salaries, operational expenses, and more.', 'shopmetrics'); ?>
        </p>
        
        <div class="sm-integration-grid">
            <!-- QuickBooks Online -->
            <div class="sm-integration-card">
                <div class="sm-integration-logo">
                    <img src="<?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'admin/images/quickbooks-logo.svg'); ?>"
                         onerror="this.src='<?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'admin/images/quickbooks-logo.png'); ?>';
                                     if (this.naturalWidth === 0) this.src='<?php echo esc_url(admin_url('images/wordpress-logo.svg')); ?>';"
                         alt="QuickBooks Online">
                </div>
                <div class="sm-integration-content">
                    <h4><?php esc_html_e('QuickBooks Online', 'shopmetrics'); ?></h4>
                    <p><?php esc_html_e('Integrate with QuickBooks to analyze financial data and calculate true business profitability.', 'shopmetrics'); ?></p>
                    <button class="button sm-connect-button" data-service="quickbooks" data-nonce="<?php echo esc_attr(wp_create_nonce('sm_connect_service_nonce')); ?>">
                        <?php esc_html_e('Connect', 'shopmetrics'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Xero -->
            <div class="sm-integration-card">
                <div class="sm-integration-logo">
                    <img src="<?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'admin/images/xero-logo.png'); ?>"
                         onerror="this.src='<?php echo esc_url(admin_url('images/wordpress-logo.svg')); ?>';"
                         alt="Xero">
                </div>
                <div class="sm-integration-content">
                    <h4><?php esc_html_e('Xero', 'shopmetrics'); ?></h4>
                    <p><?php esc_html_e('Connect with Xero accounting software to analyze business expenses and revenue.', 'shopmetrics'); ?></p>
                    <button class="button sm-connect-button" data-service="xero" data-nonce="<?php echo esc_attr(wp_create_nonce('sm_connect_service_nonce')); ?>">
                        <?php esc_html_e('Connect', 'shopmetrics'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Zoho Books -->
            <div class="sm-integration-card">
                <div class="sm-integration-logo">
                    <img src="<?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'admin/images/zoho-books-logo.svg'); ?>"
                         onerror="this.src='<?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'admin/images/zoho-books-logo.png'); ?>';
                                     if (this.naturalWidth === 0) this.src='<?php echo esc_url(admin_url('images/wordpress-logo.svg')); ?>';"
                         alt="Zoho Books">
                </div>
                <div class="sm-integration-content">
                    <h4><?php esc_html_e('Zoho Books', 'shopmetrics'); ?></h4>
                    <p><?php esc_html_e('Integrate with Zoho Books to analyze expense patterns and overall business profitability.', 'shopmetrics'); ?></p>
                    <button class="button sm-connect-button" data-service="zoho-books" data-nonce="<?php echo esc_attr(wp_create_nonce('sm_connect_service_nonce')); ?>">
                        <?php esc_html_e('Connect', 'shopmetrics'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Sage -->
            <div class="sm-integration-card">
                <div class="sm-integration-logo">
                    <img src="<?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'admin/images/sage-logo.png'); ?>"
                         onerror="this.src='<?php echo esc_url(admin_url('images/wordpress-logo.svg')); ?>';"
                         alt="Sage">
                </div>
                <div class="sm-integration-content">
                    <h4><?php esc_html_e('Sage', 'shopmetrics'); ?></h4>
                    <p><?php esc_html_e('Connect with Sage accounting software for comprehensive financial analysis.', 'shopmetrics'); ?></p>
                    <button class="button sm-connect-button" data-service="sage" data-nonce="<?php echo esc_attr(wp_create_nonce('sm_connect_service_nonce')); ?>">
                        <?php esc_html_e('Connect', 'shopmetrics'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Hide future integration sections for initial release
    $('.sm-settings-section[data-integration="ads-platforms"], .sm-settings-section[data-integration="accounting-platforms"]').hide();
    
    // Manual snapshot trigger
    $('#manual-snapshot-trigger').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var statusSpan = $('#manual-snapshot-status');
        
        button.prop('disabled', true);
        statusSpan.text('<?php echo esc_js(__('Taking snapshot...', 'shopmetrics')); ?>').show();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shopmetrics_manual_snapshot',
                nonce: button.data('nonce')
            },
            success: function(response) {
                if (response.success) {
                    statusSpan.text(response.data.message).css('color', 'green');
                } else {
                    statusSpan.text(response.data.message).css('color', 'red');
                }
            },
            error: function() {
                statusSpan.text('<?php echo esc_js(__('An error occurred. Please try again.', 'shopmetrics')); ?>').css('color', 'red');
            },
            complete: function() {
                button.prop('disabled', false);
                setTimeout(function() {
                    statusSpan.fadeOut();
                }, 5000);
            }
        });
    });
    
    // Fix snapshot schedule
    $('#fix-snapshot-schedule').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        button.text('<?php echo esc_js(__('Fixing...', 'shopmetrics')); ?>').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shopmetrics_fix_snapshot_schedule',
                nonce: button.data('nonce')
            },
            success: function(response) {
                if (response.success) {
                    location.reload(); // Reload to show updated schedule
                } else {
                    alert(response.data.message);
                    button.text('<?php echo esc_js(__('Fix Schedule', 'shopmetrics')); ?>').prop('disabled', false);
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('An error occurred. Please try again.', 'shopmetrics')); ?>');
                button.text('<?php echo esc_js(__('Fix Schedule', 'shopmetrics')); ?>').prop('disabled', false);
            }
        });
    });
    
    // Connect buttons for integrations
    $('.sm-connect-button').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var service = button.data('service');
        
        // For now, just show a coming soon message
        alert('<?php echo esc_js(__('Integration coming soon! This feature is currently under development.', 'shopmetrics')); ?>');
    });
    

});
</script> 