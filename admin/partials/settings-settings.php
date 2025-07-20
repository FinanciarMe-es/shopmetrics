<?php
/**
 * Settings page template.
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

// Get required data
$api_token = get_option('shopmetrics_analytics_api_token', '');
$site_identifier = esc_url_raw(site_url());
$plugin_name = isset($this->plugin_name) ? $this->plugin_name : 'shopmetrics';

// Get analytics settings array
$settings = get_option('shopmetrics_settings', array());
$log_nonce = wp_create_nonce('shopmetrics_settings_ajax_nonce');
?>

<div class="wrap">
    <div class="shopmetrics-settings-section">
        <h2 style="display: flex; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
            <span class="dashicons dashicons-admin-site-alt3" style="font-size: 24px; width: 24px; height: 24px; margin-right: 10px; color: #2271b1;"></span>
            <?php esc_html_e('ShopMetrics Server Connection', 'shopmetrics'); ?>
        </h2>
        
        <?php if (!empty($api_token)): ?>
            <!-- Site is connected -->
            <div class="connection-status-visual" style="display: flex; align-items: center; margin-bottom: 20px;">
                <!--  Plugin asset image: not in Media Library, so <img> is used instead of wp_get_attachment_image() -->
                <img src="<?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'admin/images/financiarme-logo.svg'); ?>" alt="FinanciarMe" style="height: 40px;">
                <span style="color: #f1c40f; font-size: 32px; margin: 0 20px; display: flex; align-items: center; justify-content: center;">⚡</span>
                <!--  Plugin asset image: not in Media Library, so <img> is used instead of wp_get_attachment_image() -->
                <img src="<?php echo esc_url(includes_url('images/w-logo-blue.png')); ?>" alt="WordPress" style="height: 40px;">
            </div>
            <p>
                <?php esc_html_e('Status:', 'shopmetrics'); ?> 
                <strong style="color: green;"><?php esc_html_e('Connected', 'shopmetrics'); ?></strong>
                <span class="connection-info-icon dashicons dashicons-info-outline" style="color: #2271b1; cursor: pointer; font-size: 16px; vertical-align: middle; margin-left: 5px;"></span>
            </p>
            
            <!-- Hidden connection details that will be shown in the popup -->
            <div id="connection-details-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 10000;">
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; width: 90%;">
                    <h3 style="margin-top: 0;"><?php esc_html_e('Connection Details', 'shopmetrics'); ?></h3>
                    <?php
                    // Mask the token for display
                    $masked_token = substr($api_token, 0, 9) . '...' . substr($api_token, -5);
                    ?>
                    <p><strong><?php esc_html_e('Site Identifier:', 'shopmetrics'); ?></strong> <code><?php echo esc_html($site_identifier); ?></code></p>
                    <p><strong><?php esc_html_e('API Token:', 'shopmetrics'); ?></strong> <code><?php echo esc_html($masked_token); ?></code></p>
                    <p><em><?php esc_html_e('This information could be useful for the FinanciarMe support team.', 'shopmetrics'); ?></em></p>
                    <button id="close-connection-details" class="button" style="margin-top: 10px;"><?php esc_html_e('Close', 'shopmetrics'); ?></button>
                </div>
            </div>
            
            <!-- Sync Button -->
            <div style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 15px;">
                <p><?php esc_html_e('Manually trigger a synchronization of all historical order data. This runs in the background and may take time depending on the number of orders.', 'shopmetrics'); ?></p>
                <button id="shopmetrics_start_sync" class="button" data-nonce="<?php echo esc_attr(wp_create_nonce('shopmetrics_api_actions')); ?>"><?php esc_html_e('Sync Historical Data', 'shopmetrics'); ?></button>
                <span id="shopmetrics-sync-status" style="margin-left: 10px;"></span>
            </div>
            
            <!-- Disconnect Button -->
            <div style="border: 1px solid red; padding: 10px; margin-top: 15px;">
                <p style="color: red; font-weight: bold;"><?php esc_html_e('Dangerous Zone!', 'shopmetrics'); ?></p>
                <p><?php esc_html_e('Disconnecting your site will remove the local API token. Reconnecting may be required to resume data synchronization. A future update will allow full data deletion from FinanciarMe servers upon disconnect.', 'shopmetrics'); ?></p>
                
                <!-- Nonce field for disconnect action -->
                <input type="hidden" id="shopmetrics_disconnect_nonce" name="shopmetrics_disconnect_nonce" value="<?php echo esc_attr(wp_create_nonce('shopmetrics_disconnect_action')); ?>" />
                
                <button id="shopmetrics-disconnect-button" class="button" style="background-color: red; color: white; border-color: darkred;">
                    <?php esc_html_e('Disconnect Site', 'shopmetrics'); ?>
                </button>
                <span id="shopmetrics-disconnect-status" style="margin-left: 10px;"></span>
            </div>
            
            <?php
            // Modal open/close JS moved to wp_add_inline_script in admin_enqueue_scripts for settings page
            ?>
            
        <?php else: ?>
            <!-- Site is not connected -->
            <div class="connection-status-visual" style="display: flex; align-items: center; margin-bottom: 20px;">
                <!--  Plugin asset image: not in Media Library, so <img> is used instead of wp_get_attachment_image() -->
                <img src="<?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'admin/images/financiarme-logo.svg'); ?>" alt="FinanciarMe" style="height: 40px;">
                <span style="color: #d63638; font-size: 32px; margin: 0 20px; display: flex; flex-direction: column; align-items: center;">
                    <span style="font-size: 28px; line-height: 1;">✕</span>
                    <span style="border-top: 2px dashed #d63638; width: 30px; transform: rotate(-45deg); position: relative; top: -14px;"></span>
                </span>
                <!--  Plugin asset image: not in Media Library, so <img> is used instead of wp_get_attachment_image() -->
                <img src="<?php echo esc_url(includes_url('images/w-logo-blue.png')); ?>" alt="WordPress" style="height: 40px;">
            </div>
            <p><?php esc_html_e('Status:', 'shopmetrics'); ?> <strong style="color: red;"><?php esc_html_e('Not Connected', 'shopmetrics'); ?></strong></p>
            <p><?php esc_html_e('To start using ShopMetrics, connect your site. This will securely register your site and begin an initial synchronization of your historical order data.', 'shopmetrics'); ?></p>
            
            <!-- Hidden connection fields for AJAX -->
            <input type="hidden" name="api_token" id="api_token" value="<?php echo esc_attr(get_option('shopmetrics_analytics_api_token', '')); ?>" />
            <input type="hidden" name="site_identifier" id="site_identifier" value="<?php echo esc_attr(get_option('shopmetrics_analytics_site_identifier', '')); ?>" />
            
            <button id="connect-button" class="button button-primary" style="background-color: #2271b1; border-color: #1a5a8e; color: white;">
                <?php esc_html_e('Connect & Synchronize', 'shopmetrics'); ?>
            </button>
            <div id="connection-status" style="margin-top: 10px; display: none;"></div>
        <?php endif; ?>
    </div>

    <div class="shopmetrics-settings-section">
        <h2 style="display: flex; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
            <span class="dashicons dashicons-admin-tools" style="font-size: 24px; width: 24px; height: 24px; margin-right: 10px; color: #2271b1;"></span>
            <?php esc_html_e('Features & Subscription setup', 'shopmetrics'); ?>
        </h2>
        
        <p><?php esc_html_e('Access more features and configuration options in the dedicated sections:', 'shopmetrics'); ?></p>
        
        <div class="shopmetrics-feature-grid">
            <!-- Data Sources box -->
            <div class="shopmetrics-feature-card">
                <div class="shopmetrics-feature-header">
                    <span class="dashicons dashicons-database"></span>
                    <h3><?php esc_html_e('Data Sources', 'shopmetrics'); ?></h3>
                </div>
                <p><?php esc_html_e('Configure visit tracking, connect external data providers, and manage inventory synchronization.', 'shopmetrics'); ?></p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $plugin_name . '-data-sources')); ?>" class="button">
                    <?php esc_html_e('Manage Data Sources', 'shopmetrics'); ?>
                </a>
            </div>
            
            <!-- Cart Recovery box -->
            <div class="shopmetrics-feature-card">
                <div class="shopmetrics-feature-header">
                    <span class="dashicons dashicons-cart"></span>
                    <h3><?php esc_html_e('Cart Recovery', 'shopmetrics'); ?></h3>
                </div>
                <p><?php esc_html_e('Configure automatic cart recovery emails and reduce abandoned carts.', 'shopmetrics'); ?></p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $plugin_name . '-cart-recovery')); ?>" class="button">
                    <?php esc_html_e('Configure Cart Recovery', 'shopmetrics'); ?>
                </a>
            </div>
            
            <!-- Subscription box -->
            <div class="shopmetrics-feature-card">
                <div class="shopmetrics-feature-header">
                    <span class="dashicons dashicons-money-alt"></span>
                    <h3><?php esc_html_e('Subscription', 'shopmetrics'); ?></h3>
                </div>
                <p><?php esc_html_e('Manage your subscription plan and billing information.', 'shopmetrics'); ?></p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $plugin_name . '-subscription')); ?>" class="button">
                    <?php esc_html_e('Manage Subscription', 'shopmetrics'); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Plugin Improvement Section -->
    <div class="shopmetrics-settings-section">
        <h2 style="display: flex; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
            <span class="dashicons dashicons-performance" style="font-size: 24px; width: 24px; height: 24px; margin-right: 10px; color: #2271b1;"></span>
            <?php esc_html_e('Plugin Improvement', 'shopmetrics'); ?>
        </h2>
        
        <form id="plugin-improvement-form">
            <?php wp_nonce_field('shopmetrics_save_analytics_consent', 'analytics_consent_nonce'); ?>
            <label>
                <input type="hidden" name="shopmetrics_settings[analytics_consent]" value="0" />
                <input type="checkbox" name="shopmetrics_settings[analytics_consent]" value="1" <?php checked(!empty($settings['analytics_consent'])); ?>>
                <strong><?php esc_html_e('Help improve ShopMetrics plugin', 'shopmetrics'); ?></strong>
            </label>
            <p class="description">
                <?php esc_html_e('Share anonymous usage data to help us improve the plugin functionality and fix issues. No personal or store data is collected - only plugin performance metrics.', 'shopmetrics'); ?>
            </p>
            <div class="shopmetrics-privacy-details" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                <h4><?php esc_html_e('What we collect for plugin improvement:', 'shopmetrics'); ?></h4>
                <ul style="margin-left: 20px;">
                    <li><?php esc_html_e('Plugin feature usage patterns (which features are used most)', 'shopmetrics'); ?></li>
                    <li><?php esc_html_e('Technical environment info (WordPress version, PHP version)', 'shopmetrics'); ?></li>
                    <li><?php esc_html_e('Plugin error logs for debugging and bug fixes', 'shopmetrics'); ?></li>
                    <li><?php esc_html_e('Plugin performance metrics', 'shopmetrics'); ?></li>
                </ul>
                <h4><?php esc_html_e('What we DON\'T collect:', 'shopmetrics'); ?></h4>
                <ul style="margin-left: 20px;">
                    <li><?php esc_html_e('Personal information', 'shopmetrics'); ?></li>
                    <li><?php esc_html_e('Customer data', 'shopmetrics'); ?></li>
                    <li><?php esc_html_e('Order details', 'shopmetrics'); ?></li>
                    <li><?php esc_html_e('Financial information', 'shopmetrics'); ?></li>
                    <li><?php esc_html_e('Site content or pages', 'shopmetrics'); ?></li>
                </ul>
                <p><em><?php esc_html_e('All data is processed securely in EU servers (GDPR compliant) and used solely to improve plugin quality.', 'shopmetrics'); ?></em></p>
            </div>
            <?php if (!empty($settings['analytics_consent'])): ?>
                <div style="margin-top: 15px;">
                    <p><strong>✅ Plugin Improvement Active:</strong> Anonymous usage data is being collected to help improve the plugin.</p>
                    <p><em>This helps us identify issues, optimize performance, and prioritize new features.</em></p>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 20px;">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Save Settings', 'shopmetrics'); ?>
                </button>
                <div id="analytics-save-status" style="margin-left: 10px; display: inline-block;"></div>
            </div>
        </form>
    </div>

    <!-- Support Section -->
    <div class="shopmetrics-settings-section">
        <h2 style="display: flex; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
            <span class="dashicons dashicons-sos" style="font-size: 24px; width: 24px; height: 24px; margin-right: 10px; color: #2271b1;"></span>
            <?php esc_html_e('Support', 'shopmetrics'); ?>
        </h2>
        
        <p><?php esc_html_e('Need help with ShopMetrics? Our support team is here to assist you.', 'shopmetrics'); ?></p>
        
        <div style="margin-top: 15px;">
            <button id="shopmetrics-contact-support" class="button button-primary" style="display: flex; align-items: center;" data-analytics-event="button_clicked" data-analytics-properties='{"button":"support_contact","context":"settings"}'>
                <span class="dashicons dashicons-email-alt" style="margin-right: 5px; line-height: 1;"></span>
                <?php esc_html_e('Send Support Message', 'shopmetrics'); ?>
            </button>
            <p style="margin-top: 10px; color: #666; font-style: italic;">
                <?php esc_html_e('This will open your email client with a pre-filled support request.', 'shopmetrics'); ?>
            </p>
        </div>
    </div>

    <!-- Onboarding Section -->
    <div class="shopmetrics-settings-section">
        <h2 style="display: flex; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
            <span class="dashicons dashicons-admin-generic" style="font-size: 24px; width: 24px; height: 24px; margin-right: 10px; color: #2271b1;"></span>
            <?php esc_html_e('Onboarding', 'shopmetrics'); ?>
        </h2>
        
        <p><?php esc_html_e('Reset the setup wizard to reconfigure your ShopMetrics settings.', 'shopmetrics'); ?></p>
        
        <?php 
        		$needs_onboarding = get_option('shopmetrics_needs_onboarding', 'true') === 'true';
		if (!$needs_onboarding): 
        ?>
            <div style="margin-top: 15px;">
                <button id="shopmetrics-reset-onboarding" class="button button-secondary" style="display: flex; align-items: center;">
                    <span class="dashicons dashicons-update" style="margin-right: 5px; line-height: 1;"></span>
                    <?php esc_html_e('Reset Onboarding Wizard', 'shopmetrics'); ?>
                </button>
                <p style="margin-top: 10px; color: #666; font-style: italic;">
                    <?php esc_html_e('This will restart the setup wizard on your next visit to the dashboard.', 'shopmetrics'); ?>
                </p>
                <div id="reset-onboarding-status" style="margin-top: 10px;"></div>
            </div>
        <?php else: ?>
            <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
                <p style="margin: 0; color: #856404;">
                    <span class="dashicons dashicons-info" style="margin-right: 5px;"></span>
                    <?php esc_html_e('Onboarding wizard has not been completed yet. Visit the dashboard to start setup.', 'shopmetrics'); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Log Management Section (Hidden) -->
    <div class="shopmetrics-settings-section" style="display: none;">
        <h2 style="display: flex; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
            <span class="dashicons dashicons-text-page" style="font-size: 24px; width: 24px; height: 24px; margin-right: 10px; color: #2271b1;"></span>
            <?php esc_html_e('Log Management', 'shopmetrics'); ?>
        </h2>
    </div>

    <!-- Debug & Logging Section -->
    <div class="shopmetrics-settings-section" style="margin-top: 40px;">
        <h2 style="display: flex; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
            <span class="dashicons dashicons-admin-generic" style="font-size: 24px; width: 24px; height: 24px; margin-right: 10px; color: #2271b1;"></span>
            <?php esc_html_e('Debug & Logging', 'shopmetrics'); ?>
        </h2>
        <form method="post" action="options.php">
            <?php settings_fields('shopmetrics_settings_group'); ?>
            <label style="font-weight: bold;">
                <input type="hidden" name="shopmetrics_settings[enable_debug_logging]" value="0" />
                <input type="checkbox" name="shopmetrics_settings[enable_debug_logging]" value="1" <?php checked(!empty($settings['enable_debug_logging'])); ?> />
                <strong>Enable Debug Logging</strong>
            </label><br>
            <div style="margin: 8px 0 16px 24px; color: #555;">
                <div>When enabled, detailed plugin logs will be written for troubleshooting. Disable in production.</div>
                <div>Log file: <code><?php echo esc_html(\ShopMetrics_Logger::get_instance()->get_log_file()); ?></code></div>
                <div><a href="<?php echo esc_url(admin_url('admin-ajax.php?action=shopmetrics_download_logs&nonce=' . $log_nonce)); ?>" target="_blank">Download log file</a></div>
            </div>
            <!-- Скрытые поля для остальных настроек -->
            <input type="hidden" name="shopmetrics_settings[enable_visit_tracking]" value="<?php echo !empty($settings['enable_visit_tracking']) ? '1' : '0'; ?>" />
            <input type="hidden" name="shopmetrics_settings[cogs_meta_key]" value="<?php echo esc_attr($settings['cogs_meta_key'] ?? ''); ?>" />
            <input type="hidden" name="shopmetrics_settings[selected_order_blocks]" value="<?php echo esc_attr($settings['selected_order_blocks'] ?? 1); ?>" />
            <input type="hidden" name="shopmetrics_settings[cogs_default_percentage]" value="<?php echo esc_attr($settings['cogs_default_percentage'] ?? ''); ?>" />
            <!-- Low stock notifications are handled via Settings API on data sources page -->
            <input type="hidden" name="shopmetrics_settings[analytics_consent]" value="<?php echo !empty($settings['analytics_consent']) ? '1' : '0'; ?>" />
            <!-- Скрытые поля для токена и site_identifier -->
            <input type="hidden" name="shopmetrics_analytics_api_token" value="<?php echo esc_attr(get_option('shopmetrics_analytics_api_token', '')); ?>" />
            <input type="hidden" name="shopmetrics_analytics_site_identifier" value="<?php echo esc_attr(get_option('shopmetrics_analytics_site_identifier', '')); ?>" />
            <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>
        </form>
    </div>
</div> 