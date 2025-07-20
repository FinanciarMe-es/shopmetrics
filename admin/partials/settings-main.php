<h2 class="shopmetrics-settings-section-title"><?php esc_html_e('Stock Synchronization', 'shopmetrics'); ?></h2>
<div class="shopmetrics-settings-section">
    <div class="shopmetrics-settings-field">
        <h3><?php esc_html_e('Manual Inventory Snapshot', 'shopmetrics'); ?></h3>
        <p><?php esc_html_e('If your stock data is not updating automatically, you can trigger a manual snapshot here. This will collect current inventory data from your store and send it to ShopMetrics.', 'shopmetrics'); ?></p>
        
        <button id="manual-snapshot-trigger" class="button button-primary">
            <?php esc_html_e('Take Inventory Snapshot Now', 'shopmetrics'); ?>
        </button>
        <span id="manual-snapshot-status" style="display:none; margin-left: 10px;"></span>
    </div>
    
    <div class="shopmetrics-settings-field">
        <h3><?php esc_html_e('Scheduled Snapshots', 'shopmetrics'); ?></h3>
        <p><?php esc_html_e('Inventory snapshots are scheduled to run automatically once per day. This keeps your stock data up to date in the dashboard.', 'shopmetrics'); ?></p>
        <?php 
        // Check if Action Scheduler is active
        if (class_exists('ActionScheduler_Store')) {
            $next_snapshot = as_next_scheduled_action('shopmetrics_analytics_take_inventory_snapshot');
            if ($next_snapshot) {
                echo '<p><strong>' . esc_html__('Next scheduled snapshot:', 'shopmetrics') . '</strong> ' . 
                     esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_snapshot)) . '</p>';
            } else {
                echo '<p class="shopmetrics-error-text">' . esc_html__('No snapshot is currently scheduled. This may indicate an issue with your WordPress scheduled tasks.', 'shopmetrics') . '</p>';
                echo '<p><a href="#" id="fix-snapshot-schedule" class="button">' . esc_html__('Fix Schedule', 'shopmetrics') . '</a></p>';
            }
        } else {
            echo '<p class="shopmetrics-error-text">' . esc_html__('Action Scheduler is not active. This plugin is required for WooCommerce and should be active. Please check your WordPress installation.', 'shopmetrics') . '</p>';
        }
        ?>
    </div>
</div> 