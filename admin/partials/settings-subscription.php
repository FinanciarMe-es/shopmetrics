<?php
/**
 * Subscription management page template.
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

// Get subscription status and related info
$api_token = get_option('shopmetrics_analytics_api_token', '');
$site_identifier = get_option('shopmetrics_analytics_site_identifier', '');
$subscription_status = get_option('shopmetrics_subscription_status', 'free');
$subscription_plan = get_option('shopmetrics_subscription_plan', 'free');
$next_billing_date = get_option('shopmetrics_next_billing_date', '');
$cancel_at_period_end = get_option('shopmetrics_cancel_at_period_end', false);

// Legacy variables for backwards compatibility
$cancel_at = get_option('shopmetrics_cancel_at', null);

// Get pricing data from API
$pricing_data = json_decode(get_option('shopmetrics_pricing_data', ''), true);
$yearly_price = '€99'; // Default fallback
$yearly_monthly_equivalent = '€8.25'; // Default fallback

if ($pricing_data && isset($pricing_data['yearly'])) {
    $yearly_price = $pricing_data['yearly']['formatted_amount'];
    $yearly_monthly_equivalent = $pricing_data['yearly']['monthly_equivalent'];
}

// Check if returning from successful Stripe checkout
$stripe_checkout_success = isset($_GET['stripe_checkout']) && sanitize_text_field( wp_unslash( $_GET['stripe_checkout'] ) ) === 'success';

// SECURITY NOTE:
// No nonce is used here because this is a callback from Stripe (external service), not a user-triggered action.
// The only effect is to sync subscription status and update the display; no sensitive data is modified based on user input.
if ($stripe_checkout_success && !empty($api_token) && !empty($site_identifier)) {
    // Get admin instance and force sync
    global $shopmetricsanalytics_admin;
    if ($shopmetricsanalytics_admin) {
        // Use reflection to call private method
        $reflection = new ReflectionClass($shopmetricsanalytics_admin);
        $syncMethod = $reflection->getMethod('sync_subscription_info');
        $syncMethod->setAccessible(true);
        $syncMethod->invoke($shopmetricsanalytics_admin, $site_identifier, $api_token);
        
        // Re-fetch the updated data
        $subscription_status = get_option('shopmetrics_subscription_status', 'free');
        $next_billing_date = get_option('shopmetrics_next_billing_date', '');
    }
}

// Calculate data retention info for free tier
$free_tier_retention_days = 90; // 3 months
$oldest_data_date = gmdate('Y-m-d', strtotime('-' . $free_tier_retention_days . ' days'));
$deletion_warning_date = gmdate('Y-m-d', strtotime('+7 days', strtotime($oldest_data_date)));

// Subscription status display
$status_display = ucfirst($subscription_status);
if ($subscription_status === 'active' || $subscription_status === 'paid') {
    $status_class = 'shopmetrics-status-active';
    $status_icon = 'yes-alt';
    $status_display = 'Paid Plan';
} elseif ($subscription_status === 'free' || $subscription_status === 'cancelled') {
    $status_class = 'shopmetrics-status-free';
    $status_icon = 'admin-users';
    $status_display = 'Free Plan';
} elseif ($subscription_status === 'pending_cancellation') {
    $status_class = 'shopmetrics-status-pending-cancel';
    $status_icon = 'warning';
} else {
    $status_class = 'shopmetrics-status-unknown';
    $status_icon = 'warning';
}

// Default order blocks (1-10)
// Order blocks functionality removed - using flat pricing model
?>

<div class="wrap">
    <!-- Status Block (Top Section) -->
    <div class="shopmetrics-status-section">
        <h2 class="shopmetrics-section-title">
            <span class="dashicons dashicons-businessman"></span>
            <?php esc_html_e('Subscription Status', 'shopmetrics'); ?>
        </h2>

        <?php if ($subscription_status === 'free' || $subscription_status === 'cancelled') : ?>
            <!-- Free and Premium Plans Comparison -->
            <div class="shopmetrics-plan-comparison">
                <!-- Free Plan Column -->
                <div class="shopmetrics-comparison-column shopmetrics-free-column">
                    <div class="shopmetrics-status-card shopmetrics-status-free">
                        <div class="shopmetrics-status-header">
                            <div class="shopmetrics-status-badge">
                                <?php esc_html_e('Free Plan', 'shopmetrics'); ?>
                            </div>
                            <div class="shopmetrics-current-label"><?php esc_html_e('Currently Used', 'shopmetrics'); ?></div>
                        </div>
                        
                        <div class="shopmetrics-free-features">
                            <div class="shopmetrics-main-limitation">
                                <span class="shopmetrics-feature-icon dashicons dashicons-clock"></span>
                                <strong><?php esc_html_e('Limited Data Retention', 'shopmetrics'); ?></strong>
                            </div>
                            
                            <div class="shopmetrics-limitation-details">
                                <p>
                                    <?php 
                                    // translators: %1$d is the number of days, %2$s is the oldest data date
                                    echo sprintf(esc_html__('Your data is automatically deleted after %1$d days. Data older than %2$s will be removed.', 'shopmetrics'),
                                        esc_html($free_tier_retention_days),
                                        esc_html($oldest_data_date)
                                    ); 
                                    ?>
                                </p>
                                <p class="shopmetrics-upgrade-hint">
                                    <strong><?php esc_html_e('Upgrade to Premium for unlimited data retention!', 'shopmetrics'); ?></strong>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Premium Plan Column -->
                <div class="shopmetrics-comparison-column shopmetrics-premium-column">
                    <div class="shopmetrics-status-card shopmetrics-status-premium">
                        <div class="shopmetrics-status-header">
                            <div class="shopmetrics-status-badge">
                                <?php esc_html_e('Premium Plan', 'shopmetrics'); ?>
                            </div>
                            <div class="shopmetrics-recommended-label"><?php esc_html_e('Recommended', 'shopmetrics'); ?></div>
                        </div>
                        
                        <div class="shopmetrics-pricing-info">
                            <div class="shopmetrics-price"><?php echo esc_html($yearly_price); ?></div>
                            <div class="shopmetrics-price-period"><?php esc_html_e('per year', 'shopmetrics'); ?></div>
                        </div>
                        
                        <div class="shopmetrics-premium-features">
                            <div class="shopmetrics-main-feature">
                                <span class="shopmetrics-feature-icon dashicons dashicons-database-view"></span>
                                <strong><?php esc_html_e('Unlimited data retention', 'shopmetrics'); ?></strong>
                            </div>
                            
                            <div class="shopmetrics-features-grid">
                                <div class="shopmetrics-feature-column">
                                    <div class="shopmetrics-feature">
                                        <span class="shopmetrics-feature-icon dashicons dashicons-chart-area"></span>
                                        <?php esc_html_e('Complete Sales Analytics', 'shopmetrics'); ?>
                                    </div>
                                    <div class="shopmetrics-feature">
                                        <span class="shopmetrics-feature-icon dashicons dashicons-money-alt"></span>
                                        <?php esc_html_e('Finance Dashboard', 'shopmetrics'); ?>
                                    </div>
                                    <div class="shopmetrics-feature">
                                        <span class="shopmetrics-feature-icon dashicons dashicons-products"></span>
                                        <?php esc_html_e('Stock Management', 'shopmetrics'); ?>
                                    </div>
                                    <div class="shopmetrics-feature">
                                        <span class="shopmetrics-feature-icon dashicons dashicons-cart"></span>
                                        <?php esc_html_e('Cart Recovery System', 'shopmetrics'); ?>
                                    </div>
                                </div>
                                <div class="shopmetrics-feature-column">
                                    <div class="shopmetrics-feature">
                                        <span class="shopmetrics-feature-icon dashicons dashicons-megaphone"></span>
                                        <?php esc_html_e('Marketing Campaign Tracking', 'shopmetrics'); ?>
                                    </div>
                                    <div class="shopmetrics-feature">
                                        <span class="shopmetrics-feature-icon dashicons dashicons-chart-line"></span>
                                        <?php esc_html_e('Time-series Analytics', 'shopmetrics'); ?>
                                    </div>
                                    <div class="shopmetrics-feature">
                                        <span class="shopmetrics-feature-icon dashicons dashicons-sos"></span>
                                        <?php esc_html_e('Priority Technical Support', 'shopmetrics'); ?>
                                    </div>
                                    <div class="shopmetrics-feature">
                                        <span class="shopmetrics-feature-icon dashicons dashicons-admin-tools"></span>
                                        <?php esc_html_e('Advanced Reporting', 'shopmetrics'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="shopmetrics-upgrade-action">
                            <button id="shopmetrics-subscribe-premium" class="button button-primary button-hero">
                                <?php esc_html_e('Upgrade to Premium', 'shopmetrics'); ?>
                            </button>
                            <span id="shopmetrics-subscribe-premium-status" class="shopmetrics-action-status"></span>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($subscription_status === 'active' || $subscription_status === 'paid') : ?>
            <!-- Paid Tier Display -->
            <div class="shopmetrics-status-card shopmetrics-status-paid shopmetrics-status-green-container">
                <div class="shopmetrics-status-header">
                    <div class="shopmetrics-status-badge">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('Premium Plan', 'shopmetrics'); ?>
                    </div>
                </div>

                <div class="shopmetrics-paid-plan-content">
                    <div class="shopmetrics-subscription-info-box">
                        <div class="shopmetrics-benefit-icon">
                            <span class="dashicons dashicons-database-view"></span>
                        </div>
                        <div class="shopmetrics-benefit-content">
                            <h4><?php esc_html_e('Unlimited Data Storage', 'shopmetrics'); ?></h4>
                            <p><?php esc_html_e('Your data is stored permanently with no automatic deletion. Perfect for long-term analysis and reporting.', 'shopmetrics'); ?></p>
                        </div>
                    </div>

                    <div class="shopmetrics-billing-info-box">
                        <div class="shopmetrics-billing-icon">
                            <span class="dashicons dashicons-clock"></span>
                        </div>
                        <div class="shopmetrics-billing-content">
                            <h4><?php esc_html_e('Billing Information', 'shopmetrics'); ?></h4>
                            <div class="shopmetrics-subscription-details">
                                <div class="shopmetrics-detail-row">
                                    <span class="shopmetrics-detail-label"><?php esc_html_e('Next Billing Date:', 'shopmetrics'); ?></span>
                                    <span class="shopmetrics-detail-value"><?php 
                                        if ($next_billing_date && is_numeric($next_billing_date)) {
                                            echo esc_html(wp_date('F j, Y', $next_billing_date));
                                        } else {
                                            echo esc_html__('Not available', 'shopmetrics');
                                        }
                                    ?></span>
                                </div>
                                <div class="shopmetrics-detail-row">
                                    <span class="shopmetrics-detail-label"><?php esc_html_e('Billing Cycle:', 'shopmetrics'); ?></span>
                                    <span class="shopmetrics-detail-value"><?php esc_html_e('Annual', 'shopmetrics'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="shopmetrics-subscription-actions">
                    <div class="shopmetrics-actions-right">
                        <button id="shopmetrics-cancel-subscription" class="button button-secondary shopmetrics-cancel-button" data-nonce="<?php echo esc_attr(wp_create_nonce('shopmetrics_settings_ajax_nonce')); ?>">
                            <?php esc_html_e('Cancel Subscription', 'shopmetrics'); ?>
                        </button>
                    </div>
                </div>
            </div>

        <?php elseif ($subscription_status === 'pending_cancellation') : ?>
            <!-- Pending Cancellation Display -->
            <div class="shopmetrics-status-card shopmetrics-status-pending-cancel">
                <div class="shopmetrics-status-header">
                    <div class="shopmetrics-status-badge">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e('Cancellation Pending', 'shopmetrics'); ?>
                    </div>
                </div>
                
                <div class="shopmetrics-cancellation-notice">
                    <p><?php esc_html_e('Your subscription is scheduled for cancellation at the end of your current billing period.', 'shopmetrics'); ?></p>
                    <?php if ($next_billing_date && is_numeric($next_billing_date)) : ?>
                        <p><strong><?php 
                            // translators: %s is the date when access continues until
                            printf(esc_html__('Access continues until: %s', 'shopmetrics'), esc_html(date_i18n('F j, Y', $next_billing_date))); 
                        ?></strong></p>
                    <?php endif; ?>
                    <p><?php esc_html_e('After cancellation, your account will switch to the Free plan with 90-day data retention.', 'shopmetrics'); ?></p>
                </div>

                <div class="shopmetrics-subscription-actions">
                    <button id="shopmetrics-reactivate-subscription" class="button button-primary" data-nonce="<?php echo esc_attr(wp_create_nonce('shopmetrics_settings_ajax_nonce')); ?>">
                        <?php esc_html_e('Reactivate Subscription', 'shopmetrics'); ?>
                    </button>
                    <span id="shopmetrics-reactivate-subscription-status" class="shopmetrics-action-status"></span>
                </div>
            </div>

        <?php else : ?>
            <!-- Unknown Status (not free, active, paid, pending_cancellation, or cancelled) -->
            <div class="shopmetrics-status-card shopmetrics-status-cancelled">
                <div class="shopmetrics-status-header">
                    <div class="shopmetrics-status-badge">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e('Unknown Status', 'shopmetrics'); ?>
                    </div>
                </div>
                <p><?php esc_html_e('Your subscription status is unclear. Please contact support.', 'shopmetrics'); ?></p>
                <p><?php esc_html_e('Current status:', 'shopmetrics'); ?> <code><?php echo esc_html($subscription_status); ?></code></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Billing History Block -->
    <div class="shopmetrics-billing-section">
        <h2 class="shopmetrics-section-title">
            <span class="dashicons dashicons-money-alt"></span>
            <?php esc_html_e('Billing History', 'shopmetrics'); ?>
        </h2>
        <div id="shopmetrics-billing-history-container">
            <!-- Billing history will be loaded via AJAX -->
            <p class="shopmetrics-loading"><?php esc_html_e('Loading billing history...', 'shopmetrics'); ?></p>
        </div>
    </div>
</div>

<!-- Cancellation Modal -->
<div id="shopmetrics-cancellation-modal" class="shopmetrics-modal">
    <div class="shopmetrics-modal-content">
        <div class="shopmetrics-modal-header">
            <h3><?php esc_html_e('Cancel Subscription', 'shopmetrics'); ?></h3>
            <span class="shopmetrics-modal-close">&times;</span>
        </div>
        <div class="shopmetrics-modal-body">
            <div class="shopmetrics-cancellation-warning">
                <p><strong><?php esc_html_e('Are you sure you want to cancel your subscription?', 'shopmetrics'); ?></strong></p>
                <p><?php esc_html_e('Your subscription will remain active until the end of your current billing period:', 'shopmetrics'); ?> <strong id="shopmetrics-billing-period-end"></strong></p>
                <p><?php esc_html_e('After cancellation, your account will switch to the Free plan with 90-day data retention.', 'shopmetrics'); ?></p>
            </div>
            
            <div class="shopmetrics-cancellation-form">
                <label for="shopmetrics-cancellation-reason">
                    <?php esc_html_e('Please tell us why you\'re cancelling (optional):', 'shopmetrics'); ?>
                </label>
                <select id="shopmetrics-cancellation-reason" name="cancellation_reason">
                    <option value=""><?php esc_html_e('Select a reason...', 'shopmetrics'); ?></option>
                    <option value="too_expensive"><?php esc_html_e('Too expensive', 'shopmetrics'); ?></option>
                    <option value="not_using"><?php esc_html_e('Not using the service enough', 'shopmetrics'); ?></option>
                    <option value="missing_features"><?php esc_html_e('Missing features I need', 'shopmetrics'); ?></option>
                    <option value="technical_issues"><?php esc_html_e('Technical issues', 'shopmetrics'); ?></option>
                    <option value="switching_solution"><?php esc_html_e('Switching to another solution', 'shopmetrics'); ?></option>
                    <option value="other"><?php esc_html_e('Other', 'shopmetrics'); ?></option>
                </select>
                
                <label for="shopmetrics-cancellation-feedback">
                    <?php esc_html_e('Additional feedback (optional):', 'shopmetrics'); ?>
                </label>
                <textarea id="shopmetrics-cancellation-feedback" name="cancellation_feedback" rows="3" placeholder="<?php esc_attr_e('Your feedback helps us improve...', 'shopmetrics'); ?>"></textarea>
            </div>
        </div>
        <div class="shopmetrics-modal-footer">
            <button class="button button-primary shopmetrics-keep-subscription-button">
                <?php esc_html_e('Keep Subscription', 'shopmetrics'); ?>
            </button>
            <button id="shopmetrics-confirm-cancellation" class="button shopmetrics-cancel-button">
                <?php esc_html_e('Confirm Cancellation', 'shopmetrics'); ?>
            </button>
            <span id="shopmetrics-modal-status" class="shopmetrics-action-status"></span>
        </div>
    </div>
</div>

<!-- Customer Type Selection Modal -->
<div id="shopmetrics-customer-type-modal" class="shopmetrics-modal">
    <div class="shopmetrics-modal-content">
        <div class="shopmetrics-modal-header">
            <h3><?php esc_html_e('Select Customer Type', 'shopmetrics'); ?></h3>
            <span class="shopmetrics-modal-close">&times;</span>
        </div>
        <div class="shopmetrics-modal-body">
            <p class="shopmetrics-customer-type-description"><?php esc_html_e('This information helps us with tax reporting and billing. Choose the option that best describes your business.', 'shopmetrics'); ?></p>
            
            <div class="shopmetrics-customer-type-options">
                <label class="shopmetrics-customer-type-option">
                    <input type="radio" name="shopmetrics_customer_type" value="B2C" checked>
                    <div class="shopmetrics-option-content">
                        <div class="shopmetrics-option-header">
                            <span class="shopmetrics-option-icon">👤</span>
                            <strong><?php esc_html_e('Individual Customer', 'shopmetrics'); ?></strong>
                        </div>
                        <p class="shopmetrics-option-description"><?php esc_html_e('Personal use, individual purchases', 'shopmetrics'); ?></p>
                    </div>
                </label>
                
                <label class="shopmetrics-customer-type-option">
                    <input type="radio" name="shopmetrics_customer_type" value="B2B">
                    <div class="shopmetrics-option-content">
                        <div class="shopmetrics-option-header">
                            <span class="shopmetrics-option-icon">🏢</span>
                            <strong><?php esc_html_e('Business Customer', 'shopmetrics'); ?></strong>
                        </div>
                        <p class="shopmetrics-option-description"><?php esc_html_e('Company purchases, business use', 'shopmetrics'); ?></p>
                    </div>
                </label>
            </div>
            
            <!-- VAT Number Field (shown for B2B customers) -->
            <div class="shopmetrics-vat-number-field" id="shopmetrics-vat-number-field">
                <label for="shopmetrics-vat-number">
                    <strong><?php esc_html_e('VAT Number', 'shopmetrics'); ?></strong>
                    <span class="shopmetrics-vat-optional"><?php esc_html_e('(Optional)', 'shopmetrics'); ?></span>
                </label>
                <input type="text" id="shopmetrics-vat-number" name="shopmetrics_vat_number" placeholder="<?php esc_attr_e('e.g., DE123456789', 'shopmetrics'); ?>" class="shopmetrics-vat-input">
                <p class="shopmetrics-vat-description">
                    <?php esc_html_e('For EU business customers: Providing a valid VAT number may exempt you from VAT charges.', 'shopmetrics'); ?>
                </p>
            </div>
        </div>
        <div class="shopmetrics-modal-footer">
            <button id="shopmetrics-continue-checkout" class="button button-primary" disabled>
                <?php esc_html_e('Continue', 'shopmetrics'); ?>
            </button>
            <span id="shopmetrics-customer-type-status" class="shopmetrics-action-status"></span>
        </div>
    </div>
</div>

<?php 
/* All subscription styles have been moved to shopmetrics-admin.css for better organization */
?>

<!-- All JS for this page is now enqueued via wp_add_inline_script in the admin class. --> 