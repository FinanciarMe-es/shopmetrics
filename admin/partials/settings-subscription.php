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

// If successful checkout, force sync subscription data
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
    $status_class = 'sm-status-active';
    $status_icon = 'yes-alt';
    $status_display = 'Paid Plan';
} elseif ($subscription_status === 'free' || $subscription_status === 'cancelled') {
    $status_class = 'sm-status-free';
    $status_icon = 'admin-users';
    $status_display = 'Free Plan';
} elseif ($subscription_status === 'pending_cancellation') {
    $status_class = 'sm-status-pending-cancel';
    $status_icon = 'warning';
} else {
    $status_class = 'sm-status-unknown';
    $status_icon = 'warning';
}

// Default order blocks (1-10)
// Order blocks functionality removed - using flat pricing model
?>

<div class="wrap">
    <!-- Status Block (Top Section) -->
    <div class="sm-status-section">
        <h2 class="sm-section-title">
            <span class="dashicons dashicons-businessman"></span>
            <?php esc_html_e('Subscription Status', 'shopmetrics'); ?>
        </h2>

        <?php if ($subscription_status === 'free' || $subscription_status === 'cancelled') : ?>
            <!-- Free and Premium Plans Comparison -->
            <div class="sm-plan-comparison">
                <!-- Free Plan Column -->
                <div class="sm-comparison-column sm-free-column">
                    <div class="sm-status-card sm-status-free">
                        <div class="sm-status-header">
                            <div class="sm-status-badge">
                                <?php esc_html_e('Free Plan', 'shopmetrics'); ?>
                            </div>
                            <div class="sm-current-label"><?php esc_html_e('Currently Used', 'shopmetrics'); ?></div>
                        </div>
                        
                        <div class="sm-free-features">
                            <div class="sm-main-limitation">
                                <span class="sm-feature-icon dashicons dashicons-clock"></span>
                                <strong><?php esc_html_e('Limited Data Retention', 'shopmetrics'); ?></strong>
                            </div>
                            
                            <div class="sm-limitation-details">
                                <p>
                                    <?php 
                                    // translators: %1$d is the number of days, %2$s is the oldest data date
                                    echo sprintf(esc_html__('Your data is automatically deleted after %1$d days. Data older than %2$s will be removed.', 'shopmetrics'),
                                        esc_html($free_tier_retention_days),
                                        esc_html($oldest_data_date)
                                    ); 
                                    ?>
                                </p>
                                <p class="sm-upgrade-hint">
                                    <strong><?php esc_html_e('Upgrade to Premium for unlimited data retention!', 'shopmetrics'); ?></strong>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Premium Plan Column -->
                <div class="sm-comparison-column sm-premium-column">
                    <div class="sm-status-card sm-status-premium">
                        <div class="sm-status-header">
                            <div class="sm-status-badge">
                                <?php esc_html_e('Premium Plan', 'shopmetrics'); ?>
                            </div>
                            <div class="sm-recommended-label"><?php esc_html_e('Recommended', 'shopmetrics'); ?></div>
                        </div>
                        
                        <div class="sm-pricing-info">
                            <div class="sm-price"><?php echo esc_html($yearly_price); ?></div>
                            <div class="sm-price-period"><?php esc_html_e('per year', 'shopmetrics'); ?></div>
                        </div>
                        
                        <div class="sm-premium-features">
                            <div class="sm-main-feature">
                                <span class="sm-feature-icon dashicons dashicons-database-view"></span>
                                <strong><?php esc_html_e('Unlimited data retention', 'shopmetrics'); ?></strong>
                            </div>
                            
                            <div class="sm-features-grid">
                                <div class="sm-feature-column">
                                    <div class="sm-feature">
                                        <span class="sm-feature-icon dashicons dashicons-chart-area"></span>
                                        <?php esc_html_e('Complete Sales Analytics', 'shopmetrics'); ?>
                                    </div>
                                    <div class="sm-feature">
                                        <span class="sm-feature-icon dashicons dashicons-money-alt"></span>
                                        <?php esc_html_e('Finance Dashboard', 'shopmetrics'); ?>
                                    </div>
                                    <div class="sm-feature">
                                        <span class="sm-feature-icon dashicons dashicons-products"></span>
                                        <?php esc_html_e('Stock Management', 'shopmetrics'); ?>
                                    </div>
                                    <div class="sm-feature">
                                        <span class="sm-feature-icon dashicons dashicons-cart"></span>
                                        <?php esc_html_e('Cart Recovery System', 'shopmetrics'); ?>
                                    </div>
                                </div>
                                <div class="sm-feature-column">
                                    <div class="sm-feature">
                                        <span class="sm-feature-icon dashicons dashicons-megaphone"></span>
                                        <?php esc_html_e('Marketing Campaign Tracking', 'shopmetrics'); ?>
                                    </div>
                                    <div class="sm-feature">
                                        <span class="sm-feature-icon dashicons dashicons-chart-line"></span>
                                        <?php esc_html_e('Time-series Analytics', 'shopmetrics'); ?>
                                    </div>
                                    <div class="sm-feature">
                                        <span class="sm-feature-icon dashicons dashicons-sos"></span>
                                        <?php esc_html_e('Priority Technical Support', 'shopmetrics'); ?>
                                    </div>
                                    <div class="sm-feature">
                                        <span class="sm-feature-icon dashicons dashicons-admin-tools"></span>
                                        <?php esc_html_e('Advanced Reporting', 'shopmetrics'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="sm-upgrade-action">
                            <button id="sm-subscribe-premium" class="button button-primary button-hero">
                                <?php esc_html_e('Upgrade to Premium', 'shopmetrics'); ?>
                            </button>
                            <span id="sm-subscribe-premium-status" class="sm-action-status"></span>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($subscription_status === 'active' || $subscription_status === 'paid') : ?>
            <!-- Paid Tier Display -->
            <div class="sm-status-card sm-status-paid sm-status-green-container">
                <div class="sm-status-header">
                    <div class="sm-status-badge">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('Premium Plan', 'shopmetrics'); ?>
                    </div>
                </div>

                <div class="sm-paid-plan-content">
                    <div class="sm-subscription-info-box">
                        <div class="sm-benefit-icon">
                            <span class="dashicons dashicons-database-view"></span>
                        </div>
                        <div class="sm-benefit-content">
                            <h4><?php esc_html_e('Unlimited Data Storage', 'shopmetrics'); ?></h4>
                            <p><?php esc_html_e('Your data is stored permanently with no automatic deletion. Perfect for long-term analysis and reporting.', 'shopmetrics'); ?></p>
                        </div>
                    </div>

                    <div class="sm-billing-info-box">
                        <div class="sm-billing-icon">
                            <span class="dashicons dashicons-clock"></span>
                        </div>
                        <div class="sm-billing-content">
                            <h4><?php esc_html_e('Billing Information', 'shopmetrics'); ?></h4>
                            <div class="sm-subscription-details">
                                <div class="sm-detail-row">
                                    <span class="sm-detail-label"><?php esc_html_e('Next Billing Date:', 'shopmetrics'); ?></span>
                                    <span class="sm-detail-value"><?php 
                                        if ($next_billing_date && is_numeric($next_billing_date)) {
                                            echo esc_html(wp_date('F j, Y', $next_billing_date));
                                        } else {
                                            echo esc_html__('Not available', 'shopmetrics');
                                        }
                                    ?></span>
                                </div>
                                <div class="sm-detail-row">
                                    <span class="sm-detail-label"><?php esc_html_e('Billing Cycle:', 'shopmetrics'); ?></span>
                                    <span class="sm-detail-value"><?php esc_html_e('Annual', 'shopmetrics'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sm-subscription-actions">
                    <div class="sm-actions-right">
                        <button id="sm-cancel-subscription" class="button button-secondary sm-cancel-button" data-nonce="<?php echo esc_attr(wp_create_nonce('sm_settings_ajax_nonce')); ?>">
                            <?php esc_html_e('Cancel Subscription', 'shopmetrics'); ?>
                        </button>
                    </div>
                </div>
            </div>

        <?php elseif ($subscription_status === 'pending_cancellation') : ?>
            <!-- Pending Cancellation Display -->
            <div class="sm-status-card sm-status-pending-cancel">
                <div class="sm-status-header">
                    <div class="sm-status-badge">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e('Cancellation Pending', 'shopmetrics'); ?>
                    </div>
                </div>
                
                <div class="sm-cancellation-notice">
                    <p><?php esc_html_e('Your subscription is scheduled for cancellation at the end of your current billing period.', 'shopmetrics'); ?></p>
                    <?php if ($next_billing_date && is_numeric($next_billing_date)) : ?>
                        <p><strong><?php 
                            // translators: %s is the date when access continues until
                            printf(esc_html__('Access continues until: %s', 'shopmetrics'), esc_html(date_i18n('F j, Y', $next_billing_date))); 
                        ?></strong></p>
                    <?php endif; ?>
                    <p><?php esc_html_e('After cancellation, your account will switch to the Free plan with 90-day data retention.', 'shopmetrics'); ?></p>
                </div>

                <div class="sm-subscription-actions">
                    <button id="sm-reactivate-subscription" class="button button-primary" data-nonce="<?php echo esc_attr(wp_create_nonce('sm_settings_ajax_nonce')); ?>">
                        <?php esc_html_e('Reactivate Subscription', 'shopmetrics'); ?>
                    </button>
                    <span id="sm-reactivate-subscription-status" class="sm-action-status"></span>
                </div>
            </div>

        <?php else : ?>
            <!-- Unknown Status (not free, active, paid, pending_cancellation, or cancelled) -->
            <div class="sm-status-card sm-status-cancelled">
                <div class="sm-status-header">
                    <div class="sm-status-badge">
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
    <div class="sm-billing-section">
        <h2 class="sm-section-title">
            <span class="dashicons dashicons-money-alt"></span>
            <?php esc_html_e('Billing History', 'shopmetrics'); ?>
        </h2>
        <div id="sm-billing-history-container">
            <!-- Billing history will be loaded via AJAX -->
            <p class="sm-loading"><?php esc_html_e('Loading billing history...', 'shopmetrics'); ?></p>
        </div>
    </div>
</div>

<!-- Cancellation Modal -->
<div id="sm-cancellation-modal" class="sm-modal">
    <div class="sm-modal-content">
        <div class="sm-modal-header">
            <h3><?php esc_html_e('Cancel Subscription', 'shopmetrics'); ?></h3>
            <span class="sm-modal-close">&times;</span>
        </div>
        <div class="sm-modal-body">
            <div class="sm-cancellation-warning">
                <p><strong><?php esc_html_e('Are you sure you want to cancel your subscription?', 'shopmetrics'); ?></strong></p>
                <p><?php esc_html_e('Your subscription will remain active until the end of your current billing period:', 'shopmetrics'); ?> <strong id="sm-billing-period-end"></strong></p>
                <p><?php esc_html_e('After cancellation, your account will switch to the Free plan with 90-day data retention.', 'shopmetrics'); ?></p>
            </div>
            
            <div class="sm-cancellation-form">
                <label for="sm-cancellation-reason">
                    <?php esc_html_e('Please tell us why you\'re cancelling (optional):', 'shopmetrics'); ?>
                </label>
                <select id="sm-cancellation-reason" name="cancellation_reason">
                    <option value=""><?php esc_html_e('Select a reason...', 'shopmetrics'); ?></option>
                    <option value="too_expensive"><?php esc_html_e('Too expensive', 'shopmetrics'); ?></option>
                    <option value="not_using"><?php esc_html_e('Not using the service enough', 'shopmetrics'); ?></option>
                    <option value="missing_features"><?php esc_html_e('Missing features I need', 'shopmetrics'); ?></option>
                    <option value="technical_issues"><?php esc_html_e('Technical issues', 'shopmetrics'); ?></option>
                    <option value="switching_solution"><?php esc_html_e('Switching to another solution', 'shopmetrics'); ?></option>
                    <option value="other"><?php esc_html_e('Other', 'shopmetrics'); ?></option>
                </select>
                
                <label for="sm-cancellation-feedback">
                    <?php esc_html_e('Additional feedback (optional):', 'shopmetrics'); ?>
                </label>
                <textarea id="sm-cancellation-feedback" name="cancellation_feedback" rows="3" placeholder="<?php esc_attr_e('Your feedback helps us improve...', 'shopmetrics'); ?>"></textarea>
            </div>
        </div>
        <div class="sm-modal-footer">
            <button class="button button-primary sm-keep-subscription-button">
                <?php esc_html_e('Keep Subscription', 'shopmetrics'); ?>
            </button>
            <button id="sm-confirm-cancellation" class="button sm-cancel-button">
                <?php esc_html_e('Confirm Cancellation', 'shopmetrics'); ?>
            </button>
            <span id="sm-modal-status" class="sm-action-status"></span>
        </div>
    </div>
</div>

<!-- Customer Type Selection Modal -->
<div id="sm-customer-type-modal" class="sm-modal">
    <div class="sm-modal-content">
        <div class="sm-modal-header">
            <h3><?php esc_html_e('Select Customer Type', 'shopmetrics'); ?></h3>
            <span class="sm-modal-close">&times;</span>
        </div>
        <div class="sm-modal-body">
            <p class="sm-customer-type-description"><?php esc_html_e('This information helps us with tax reporting and billing. Choose the option that best describes your business.', 'shopmetrics'); ?></p>
            
            <div class="sm-customer-type-options">
                <label class="sm-customer-type-option">
                    <input type="radio" name="sm_customer_type" value="B2C" checked>
                    <div class="sm-option-content">
                        <div class="sm-option-header">
                            <span class="sm-option-icon">👤</span>
                            <strong><?php esc_html_e('Individual Customer', 'shopmetrics'); ?></strong>
                        </div>
                        <p class="sm-option-description"><?php esc_html_e('Personal use, individual purchases', 'shopmetrics'); ?></p>
                    </div>
                </label>
                
                <label class="sm-customer-type-option">
                    <input type="radio" name="sm_customer_type" value="B2B">
                    <div class="sm-option-content">
                        <div class="sm-option-header">
                            <span class="sm-option-icon">🏢</span>
                            <strong><?php esc_html_e('Business Customer', 'shopmetrics'); ?></strong>
                        </div>
                        <p class="sm-option-description"><?php esc_html_e('Company purchases, business use', 'shopmetrics'); ?></p>
                    </div>
                </label>
            </div>
            
            <!-- VAT Number Field (shown for B2B customers) -->
            <div class="sm-vat-number-field" id="sm-vat-number-field">
                <label for="sm-vat-number">
                    <strong><?php esc_html_e('VAT Number', 'shopmetrics'); ?></strong>
                    <span class="sm-vat-optional"><?php esc_html_e('(Optional)', 'shopmetrics'); ?></span>
                </label>
                <input type="text" id="sm-vat-number" name="sm_vat_number" placeholder="<?php esc_attr_e('e.g., DE123456789', 'shopmetrics'); ?>" class="sm-vat-input">
                <p class="sm-vat-description">
                    <?php esc_html_e('For EU business customers: Providing a valid VAT number may exempt you from VAT charges.', 'shopmetrics'); ?>
                </p>
            </div>
        </div>
        <div class="sm-modal-footer">
            <button id="sm-continue-checkout" class="button button-primary" disabled>
                <?php esc_html_e('Continue', 'shopmetrics'); ?>
            </button>
            <span id="sm-customer-type-status" class="sm-action-status"></span>
        </div>
    </div>
</div>

<?php 
/* All subscription styles have been moved to shopmetrics-admin.css for better organization */
?>

<script>
jQuery(document).ready(function($) {
    // Load billing history
    loadBillingHistory();
    
    // Cancel subscription - show modal
    $('#sm-cancel-subscription').on('click', function(e) {
        e.preventDefault();
        
        // Set billing period end date in modal
        var nextBillingDate = '<?php echo esc_js(esc_html($next_billing_date)); ?>';
        if (nextBillingDate && nextBillingDate !== '') {
            // Convert Unix timestamp to JavaScript date (multiply by 1000)
            var billingDate = new Date(parseInt(nextBillingDate) * 1000);
            $('#sm-billing-period-end').text(billingDate.toLocaleDateString());
        } else {
            $('#sm-billing-period-end').text('<?php echo esc_js(__('End of current period', 'shopmetrics')); ?>');
        }
        
        $('#sm-cancellation-modal').addClass('show');
    });
    
    // Close modal
    $('.sm-modal-close').on('click', function() {
        $('.sm-modal').removeClass('show');
    });
    
    // Close modal on outside click
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('sm-modal')) {
            $(e.target).removeClass('show');
        }
    });
    
    // Confirm cancellation
    $('#sm-confirm-cancellation').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var statusSpan = $('#sm-modal-status');
        var reason = $('#sm-cancellation-reason').val();
        var feedback = $('#sm-cancellation-feedback').val();
        
        // Track cancellation attempt
        if (typeof ShopMetricsAnalytics !== 'undefined') {
            ShopMetricsAnalytics.track('subscription_cancel_initiated', {
                reason: reason,
                has_feedback: feedback.length > 0
            });
        }
        
        button.prop('disabled', true);
        statusSpan.text('<?php echo esc_js(__('Processing...', 'shopmetrics')); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shopmetrics_cancel_subscription',
                nonce: '<?php echo esc_js(esc_attr(wp_create_nonce('sm_settings_ajax_nonce'))); ?>',
                reason: reason,
                feedback: feedback
            },
            success: function(response) {
                if (response.success) {
                    statusSpan.text(response.data.message).removeClass('sm-status-failed').addClass('sm-status-paid');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    statusSpan.text(response.data.message).removeClass('sm-status-paid').addClass('sm-status-failed');
                    button.prop('disabled', false);
                }
            },
            error: function() {
                statusSpan.text('<?php echo esc_js(__('An error occurred. Please try again.', 'shopmetrics')); ?>').removeClass('sm-status-paid').addClass('sm-status-failed');
                button.prop('disabled', false);
            }
        });
    });
    
    // Reactivate subscription
    $('#sm-reactivate-subscription').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('<?php echo esc_js(__('Are you sure you want to reactivate your subscription?', 'shopmetrics')); ?>')) {
            return;
        }
        
        var button = $(this);
        var statusSpan = $('#sm-reactivate-subscription-status');
        
        button.prop('disabled', true);
        statusSpan.text('<?php echo esc_js(__('Processing...', 'shopmetrics')); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shopmetrics_reactivate_subscription',
                nonce: '<?php echo esc_js(esc_attr(wp_create_nonce('sm_settings_ajax_nonce'))); ?>'
            },
            success: function(response) {
                if (response.success) {
                    statusSpan.text(response.data.message).css('color', 'green');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    statusSpan.text(response.data.message).css('color', 'red');
                    button.prop('disabled', false);
                }
            },
            error: function() {
                statusSpan.text('<?php echo esc_js(__('An error occurred. Please try again.', 'shopmetrics')); ?>').css('color', 'red');
                button.prop('disabled', false);
            }
        });
    });
    
    // Handle customer type change to show/hide VAT field
    $('input[name="sm_customer_type"]').on('change', function() {
        var customerType = $(this).val();
        var vatField = $('#sm-vat-number-field');
        var continueButton = $('#sm-continue-checkout');
        
        if (customerType === 'B2B') {
            vatField.addClass('show');
        } else {
            vatField.removeClass('show');
            $('#sm-vat-number').val(''); // Clear VAT number when switching to B2C
        }
        
        // Enable continue button when customer type is selected
        continueButton.prop('disabled', false);
    });

    // Show customer type modal when subscribe button is clicked
    $('#sm-subscribe-premium').on('click', function(e) {
        e.preventDefault();
        
        // Track subscription attempt
        if (typeof ShopMetricsAnalytics !== 'undefined') {
            ShopMetricsAnalytics.track('subscription_checkout_initiated', {
                plan: 'premium',
                source: 'subscription_page'
            });
        }
        
        // Reset modal state
        $('input[name="sm_customer_type"][value="B2C"]').prop('checked', true);
        $('#sm-vat-number-field').removeClass('show');
        $('#sm-vat-number').val('');
        $('#sm-continue-checkout').prop('disabled', false);
        
        // Show customer type modal
        $('#sm-customer-type-modal').addClass('show');
    });
    
    // Handle continue button in customer type modal
    $('#sm-continue-checkout').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var statusSpan = $('#sm-customer-type-status');
        
        // Get selected customer type and VAT number
        var customerType = $('input[name="sm_customer_type"]:checked').val() || 'B2C';
        var vatNumber = $('#sm-vat-number').val().trim();
        
        // Get WooCommerce currency 
        var currency = '<?php echo esc_js(esc_html(function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'EUR')); ?>';
        
        // Basic VAT number validation for EU format (optional)
        if (customerType === 'B2B' && vatNumber) {
            // Simple regex for common EU VAT format: 2 letters followed by 8-12 characters
            var vatRegex = /^[A-Z]{2}[A-Z0-9]{8,12}$/;
            if (!vatRegex.test(vatNumber.toUpperCase())) {
                alert('<?php echo esc_js(__('Please enter a valid VAT number format (e.g., DE123456789)', 'shopmetrics')); ?>');
                return;
            }
        }
        
        button.prop('disabled', true);
        statusSpan.text('<?php echo esc_js(__('Preparing checkout...', 'shopmetrics')); ?>');
        
        // Debug: log the nonce being sent
        var nonceValue = '<?php echo esc_js(esc_attr(wp_create_nonce('sm_settings_ajax_nonce'))); ?>';
        console.log('ShopMetrics: Sending nonce:', nonceValue);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shopmetrics_create_checkout',
                customer_type: customerType,
                vat_number: vatNumber,
                currency: currency,
                _ajax_nonce: nonceValue
            },
            success: function(response) {
                if (response.success && response.data.url) {
                    statusSpan.text('<?php echo esc_js(__('Redirecting to checkout...', 'shopmetrics')); ?>');
                    // Redirect to Stripe Checkout
                    window.location.href = response.data.url;
                } else {
                    statusSpan.text('<?php echo esc_js(__('Error', 'shopmetrics')); ?>');
                    var errorMessage = response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Failed to create checkout session.', 'shopmetrics')); ?>';
                    alert('<?php echo esc_js(__('Error:', 'shopmetrics')); ?> ' + errorMessage);
                    button.prop('disabled', false);
                    statusSpan.text('');
                }
            },
            error: function() {
                statusSpan.text('<?php echo esc_js(__('Error', 'shopmetrics')); ?>');
                alert('<?php echo esc_js(__('Network error. Please try again.', 'shopmetrics')); ?>');
                button.prop('disabled', false);
                statusSpan.text('');
            }
        });
    });
    
    // Close customer type modal
    $('.sm-modal-close').on('click', function() {
        $('.sm-modal').removeClass('show');
    });
    
    // Close modal on outside click
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('sm-modal')) {
            $(e.target).removeClass('show');
        }
    });
    
    // Keep subscription button - close modal
    $('.sm-keep-subscription-button').on('click', function() {
        $('.sm-modal').removeClass('show');
    });
    
    function loadBillingHistory() {
        var container = $('#sm-billing-history-container');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shopmetrics_get_billing_history',
                nonce: '<?php echo esc_js(wp_create_nonce('sm_settings_ajax_nonce')); ?>'
            },
            success: function(response) {
                if (response.success && response.data.history) {
                    var history = response.data.history;
                    var html = '';
                    
                    if (history.length > 0) {
                        html = '<table class="sm-billing-table">' +
                               '<thead><tr>' +
                               '<th><?php echo esc_js(__('Date', 'shopmetrics')); ?></th>' +
                               '<th><?php echo esc_js(__('Description', 'shopmetrics')); ?></th>' +
                               '<th><?php echo esc_js(__('Amount', 'shopmetrics')); ?></th>' +
                               '<th><?php echo esc_js(__('Status', 'shopmetrics')); ?></th>' +
                               '</tr></thead><tbody>';
                        
                        history.forEach(function(item) {
                            var statusClass = 'sm-status-' + item.status;
                            
                            html += '<tr>' +
                                    '<td>' + new Date(item.date * 1000).toLocaleDateString() + '</td>' +
                                    '<td>' + item.description + '</td>' +
                                    '<td>' + item.amount + '</td>' +
                                    '<td>' + item.status + '</td>' +
                                    '</tr>';
                        });
                        
                        html += '</tbody></table>';
                    } else {
                        html = '<p><?php echo esc_js(__('No billing history available.', 'shopmetrics')); ?></p>';
                    }
                    
                    container.html(html);
                } else {
                    container.html('<p><?php echo esc_js(__('Unable to load billing history at this time.', 'shopmetrics')); ?></p>');
                }
            },
            error: function() {
                container.html('<p><?php echo esc_js(__('Error loading billing history.', 'shopmetrics')); ?></p>');
            }
        });
    }
});
</script> 