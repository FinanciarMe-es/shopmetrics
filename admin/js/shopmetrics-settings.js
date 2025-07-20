/**
 * JavaScript for ShopMetrics Analytics Settings Page
 */
(function($) {
    'use strict';

    // Ensure logger is defined before any usage
    window.logger = {
        log: (...args) => {
            if (typeof shopmetricsSettings === 'undefined' || shopmetricsSettings.debugLogging) {
                console.log(...args);
            }
        },
        warn: (...args) => {
            if (typeof shopmetricsSettings === 'undefined' || shopmetricsSettings.debugLogging) {
                console.warn(...args);
            }
        },
        error: (...args) => {
            if (typeof shopmetricsSettings === 'undefined' || shopmetricsSettings.debugLogging) {
                console.error(...args);
            }
        }
    };

    // Debug initialization - log to console when script loads
    logger.log('ShopMetrics Analytics Settings script loaded');
    logger.log('jQuery version:', $.fn.jquery);
    
    // Check if ajaxurl is defined
    if (typeof ajaxurl === 'undefined') {
        logger.log('ajaxurl is not defined!');
    } else {
        logger.log('ajaxurl is defined:', ajaxurl);
    }
    
    // Log shopmetricsSettings availability
    if (typeof shopmetricsSettings === 'undefined') {
        logger.log('shopmetricsSettings is not defined!');
    } else {
        logger.log('shopmetricsSettings is defined:', shopmetricsSettings);
    }

    $(document).ready(function() {
        const disconnectButton = $('#shopmetrics-disconnect-button');
        const disconnectStatusSpan = $('#shopmetrics-disconnect-status');
        const manageBillingButton = $('#shopmetrics-manage-billing-button');
        const billingStatusSpan = $('#shopmetrics-billing-status');
        const cancelSubscriptionButton = $('#shopmetrics-cancel-subscription-button');
        const cancelStatusSpan = $('#shopmetrics-cancel-status');

        // COGS Meta Key Settings Enhancement
        const cogsDetectionResultArea = $('#shopmetrics_cogs_detection_result_area');
        const cogsManualSelectArea = $('#shopmetrics_cogs_manual_select_area');
        const cogsMetaKeyDropdown = $('#shopmetrics_cogs_meta_key_dropdown');
        const cogsHiddenInput = $('#shopmetrics_settings_cogs_meta_key_hidden_input');
        const currentCogsKeyDisplay = $('#shopmetrics_current_cogs_key_display');
        const autoDetectButton = $('#shopmetrics_auto_detect_cogs_key_button');
        const manualSelectButton = $('#shopmetrics_manual_select_cogs_key_button');

        // Function to update the saved key display and hidden input
        function updateCogsMetaKey(newKeyValue) {
            cogsHiddenInput.val(newKeyValue);
            currentCogsKeyDisplay.text(newKeyValue ? newKeyValue : (shopmetricsSettings.text_not_set || 'Not set')); 
            if (cogsMetaKeyDropdown.val() !== '_placeholder_' || newKeyValue === '') {
                cogsDetectionResultArea.hide().empty();
                cogsManualSelectArea.hide();
            }
        }

        // Auto-detect COGS Meta Key
        autoDetectButton.on('click', function() {
            cogsManualSelectArea.hide();
            cogsDetectionResultArea.show().html('<p>' + (shopmetricsSettings.text_loading || 'Loading...') + '</p>');

            $.ajax({
                url: shopmetricsSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'shopmetrics_auto_detect_cogs_key',
                    nonce: shopmetricsSettings.nonce
                },
                success: function(response) {
                    if (response.success) {
                        let html = '<p>' + response.data.message + '</p>';
                        if (response.data.detected_key) {
                            html += '<p>' + (shopmetricsSettings.text_use_this_key || 'Use this key?') + ' ';
                            html += '<button type="button" class="button button-small shopmetrics-use-detected-key" data-key="' + response.data.detected_key + '">' + (shopmetricsSettings.text_yes || 'Yes') + '</button> ';
                            html += '<button type="button" class="button button-small shopmetrics-dont-use-detected-key">' + (shopmetricsSettings.text_no || 'No') + '</button></p>';
                        } else {
                             html += '<p><button type="button" class="button button-small shopmetrics-close-detection-area">' + (shopmetricsSettings.text_ok || 'OK') + '</button></p>';
                        }
                        cogsDetectionResultArea.html(html);
                    } else {
                        cogsDetectionResultArea.html('<p class="notice notice-error">' + (response.data.message || shopmetricsSettings.error_generic || 'An error occurred.') + '</p>');
                    }
                },
                error: function() {
                    cogsDetectionResultArea.html('<p class="notice notice-error">' + (shopmetricsSettings.error_generic || 'An AJAX error occurred.') + '</p>');
                }
            });
        });

        // Handle "Yes" to use detected key
        cogsDetectionResultArea.on('click', '.shopmetrics-use-detected-key', function() {
            const detectedKey = $(this).data('key');
            updateCogsMetaKey(detectedKey);
            if (cogsManualSelectArea.is(':visible')) {
                cogsMetaKeyDropdown.val(detectedKey);
            }
            cogsDetectionResultArea.hide().empty();
        });

        // Handle "No" to use detected key or "OK"
        cogsDetectionResultArea.on('click', '.shopmetrics-dont-use-detected-key, .shopmetrics-close-detection-area', function() {
            cogsDetectionResultArea.hide().empty();
        });

        // Manual Select COGS Meta Key
        manualSelectButton.on('click', function() {
            cogsDetectionResultArea.hide().empty();
            cogsManualSelectArea.show();
            cogsMetaKeyDropdown.html('<option value="_placeholder_">' + (shopmetricsSettings.text_loading || 'Loading...') + '</option>');

            $.ajax({
                url: shopmetricsSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'shopmetrics_get_all_meta_keys',
                    nonce: shopmetricsSettings.nonce
                },
                success: function(response) {
                    if (response.success && response.data.meta_keys) {
                        cogsMetaKeyDropdown.empty(); 
                        response.data.meta_keys.forEach(function(key_obj) {
                            cogsMetaKeyDropdown.append($('<option>', {
                                value: key_obj.value,
                                text: key_obj.label
                            }));
                        });
                        cogsMetaKeyDropdown.val(cogsHiddenInput.val());
                         if (!cogsMetaKeyDropdown.val() && cogsHiddenInput.val() !== '') {
                             cogsMetaKeyDropdown.val('_placeholder_');
                        } else if (!cogsMetaKeyDropdown.val() && cogsHiddenInput.val() === '') {
                            cogsMetaKeyDropdown.val('');
                        }
                    } else {
                        cogsMetaKeyDropdown.html('<option value="_placeholder_">' + (shopmetricsSettings.error_loading_keys || 'Error loading keys') + '</option>');
                        if (response.data && response.data.message) {
                            cogsManualSelectArea.append('<p class="notice notice-error">' + response.data.message + '</p>');
                        }
                    }
                },
                error: function() {
                    cogsMetaKeyDropdown.html('<option value="_placeholder_">' + (shopmetricsSettings.error_ajax || 'AJAX Error') + '</option>');
                }
            });
        });

        // Handle dropdown change
        cogsMetaKeyDropdown.on('change', function() {
            const selectedValue = $(this).val();
            if (selectedValue !== '_placeholder_') {
                updateCogsMetaKey(selectedValue);
            } 
        });

        // --- End of COGS Meta Key Settings Enhancement ---

        // Test Recovery Email Button
        const testEmailButton = $('#shopmetrics_test_recovery_email');
        const testEmailResult = $('#shopmetrics_test_email_result');
        
        // Initialize logger (moved from inline script to proper enqueue)
        window.logger = window.logger || { log: (...args) => console.log(...args) };
        logger.log("Settings script loaded. Page loaded.");
        
        logger.log('Test button initialization. Button found:', testEmailButton.length > 0);
        logger.log('Current page info:', shopmetricsSettings.plugin_page);
        logger.log('Debug timestamp:', shopmetricsSettings.debug_timestamp);
        
        if (testEmailButton.length) {
            logger.log('Test email button found and initialized');
            
            testEmailButton.on('click', function(e) {
                logger.log('Test email button clicked');
                e.preventDefault();
                
                // Directly check for the localized nonce value
                if (typeof shopmetricsSettings === 'undefined' || typeof shopmetricsSettings.cart_recovery_nonce === 'undefined') {
                    alert('CRITICAL ERROR: The security nonce (cart_recovery_nonce) is missing. Please contact support.');
                    logger.error('shopmetricsSettings object or cart_recovery_nonce is undefined.', shopmetricsSettings);
                    return;
                }
                
                // Show loading state
                testEmailResult.show().text('Sending test email...').css({
                    'color': 'orange',
                    'display': 'inline-block'
                });
                testEmailButton.prop('disabled', true);
                
                // Make AJAX request
                logger.log('Sending AJAX request to: ' + ajaxurl);
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'shopmetrics_test_recovery_email',
                        nonce: shopmetricsSettings.cart_recovery_nonce
                    },
                    success: function(response) {
                        logger.log('AJAX response received:', response);
                        if (response.success) {
                            testEmailResult.text(response.data.message).css('color', 'green');
                            // Fade out the message after 5 seconds
                            setTimeout(function() {
                                testEmailResult.fadeOut('slow');
                            }, 5000);
                        } else {
                            testEmailResult.text('Error: ' + (response.data?.message || 'Failed to send test email')).css('color', 'red');
                        }
                        testEmailButton.prop('disabled', false);
                    },
                    error: function(xhr, status, error) {
                        logger.error('AJAX error:', status, error, xhr.responseText);
                        testEmailResult.text('AJAX Error: ' + error).css('color', 'red');
                        testEmailButton.prop('disabled', false);
                    }
                });
            });
        }

        if (disconnectButton.length) {
            logger.log('Disconnect button found and initialized');
            
            disconnectButton.on('click', function(e) {
                e.preventDefault();
                logger.log('Disconnect button clicked');

                if (!confirm('Are you sure you want to disconnect this site? This will remove the local API token.')) {
                    logger.log('User cancelled disconnect operation');
                    return;
                }

                // Get and log the nonce value for debugging
                const nonceValue = $('#shopmetrics_disconnect_nonce').val();
                logger.log('Disconnect nonce field exists:', $('#shopmetrics_disconnect_nonce').length > 0);
                logger.log('Disconnect nonce value:', nonceValue);
                
                disconnectStatusSpan.text('Disconnecting...').css('color', 'orange');
                disconnectButton.prop('disabled', true);

                const ajaxData = {
                    action: 'shopmetrics_disconnect_site',
                    _ajax_nonce: nonceValue // Get nonce value
                };
                
                logger.log('Sending AJAX request to:', ajaxurl);
                logger.log('With data:', ajaxData);

                $.post(ajaxurl, ajaxData, function(response) {
                    logger.log('Disconnect AJAX response:', response);
                    
                    if (response.success) {
                        disconnectStatusSpan.text('Disconnected successfully. Reloading...').css('color', 'green');
                        // Reload the page after a short delay
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        disconnectStatusSpan.text('Error: ' + (response.data?.message || 'Unknown error')).css('color', 'red');
                        disconnectButton.prop('disabled', false); // Re-enable button on failure
                    }
                }).fail(function(xhr, status, error) {
                    logger.error('AJAX error details:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        responseJSON: xhr.responseJSON
                    });
                    
                    disconnectStatusSpan.text('AJAX Error: ' + error).css('color', 'red');
                    disconnectButton.prop('disabled', false);
                });
            });
        }
        
        if (manageBillingButton.length) {
            manageBillingButton.on('click', function(e) {
                e.preventDefault();
                
                billingStatusSpan.text('Creating billing session...').css('color', 'orange');
                manageBillingButton.prop('disabled', true);

                const ajaxData = {
                    action: 'shopmetrics_create_checkout', // We need an AJAX wrapper
                    _ajax_nonce: $('#shopmetrics_disconnect_nonce').val() // Reuse nonce for simplicity, or create specific one
                    // We don't need to send api_token here, PHP gets it from option
                };

                // Call WP AJAX which will then call our backend API
                $.post(ajaxurl, ajaxData, function(response) {
                    if (response.success && response.data?.sessionId) {
                        billingStatusSpan.text('Redirecting to Stripe...').css('color', 'green');
                        logger.log('Received Stripe Checkout Session ID:', response.data.sessionId);
                        
                        // Check if Stripe object and publishable key are available
                        if (typeof Stripe === 'undefined') {
                             billingStatusSpan.text('Error: Stripe.js not loaded').css('color', 'red');
                             logger.error('Stripe.js not loaded.');
                             manageBillingButton.prop('disabled', false);
                             return;
                        }
                        if (!shopmetricsSettings || !shopmetricsSettings.stripe_publishable_key) {
                             billingStatusSpan.text('Error: Stripe key missing').css('color', 'red');
                             logger.error('Stripe Publishable Key not found in shopmetricsSettings.');
                             manageBillingButton.prop('disabled', false);
                             return;
                        }
                        
                        try {
                            const stripe = Stripe(shopmetricsSettings.stripe_publishable_key);
                            stripe.redirectToCheckout({
                                sessionId: response.data.sessionId
                            }).then(function (result) {
                                // If `redirectToCheckout` fails due to a browser redirect blocker,
                                // `result.error` will contain an error message.
                                if (result.error) {
                                    logger.error('Stripe redirect error:', result.error.message);
                                    billingStatusSpan.text('Error redirecting: ' + result.error.message).css('color', 'red');
                                    manageBillingButton.prop('disabled', false); // Re-enable on failure
                                }
                                // Otherwise, redirection is in progress, no need to do anything here.
                                // If successful, the user won't return to this page immediately.
                            });
                        } catch (error) {
                             logger.error('Error initializing Stripe or redirecting:', error);
                             billingStatusSpan.text('Error initializing Stripe: ' + error.message).css('color', 'red');
                             manageBillingButton.prop('disabled', false);
                        }
                        
                    } else {
                        billingStatusSpan.text('Error: ' + (response.data?.message || 'Failed to create session')).css('color', 'red');
                        manageBillingButton.prop('disabled', false);
                    }
                }).fail(function(xhr, status, error) {
                    billingStatusSpan.text('AJAX Error: ' + error).css('color', 'red');
                    manageBillingButton.prop('disabled', false);
                });
            });
        }
        
        if (cancelSubscriptionButton.length) {
            cancelSubscriptionButton.on('click', function(e) {
                e.preventDefault();

                if (!confirm('Are you sure you want to stop your subscription? It will remain active until the end of the current billing period.')) {
                    return;
                }

                cancelStatusSpan.text('Requesting cancellation...').css('color', 'orange');
                cancelSubscriptionButton.prop('disabled', true);
                
                const ajaxData = {
                    action: 'shopmetrics_cancel_subscription',
                    _ajax_nonce: $('#shopmetrics_subscription_nonce').val()
                };
                
                // Make the AJAX call
                $.post(ajaxurl, ajaxData, function(response) {
                    if (response.success) {
                        // Display success message (maybe format date if needed)
                        let successMsg = response.data?.message || 'Cancellation requested successfully.';
                        if(response.data?.cancel_at) {
                            try {
                                // Format the timestamp using browser's locale
                                const cancelDate = new Date(response.data.cancel_at * 1000);
                                successMsg += ' Subscription ends on ' + cancelDate.toLocaleDateString();
                            } catch(e) { logger.error('Error formatting cancel_at date:', e); }
                        }
                        cancelStatusSpan.text(successMsg + ' Reloading...').css('color', 'green');
                        // Reload the page after delay to show updated status/button
                        setTimeout(function() {
                            window.location.reload();
                        }, 2500);
                    } else {
                        // Display error message from backend/PHP
                        cancelStatusSpan.text('Error: ' + (response.data?.message || 'Unknown error during cancellation')).css('color', 'red');
                        cancelSubscriptionButton.prop('disabled', false); // Re-enable button on failure
                    }
                }).fail(function(xhr, status, error) {
                    // Handle AJAX communication errors
                    cancelStatusSpan.text('AJAX Error: ' + error).css('color', 'red');
                     cancelSubscriptionButton.prop('disabled', false);
                });
            });
        }

        // Handle 'Stop Subscription' button click
        $(document).on('click', '#shopmetrics-cancel-subscription-button', function(e) {
            e.preventDefault();
            const button = $(this);
            const statusSpan = $('#shopmetrics-subscription-status-message');
            const nonce = $('#shopmetrics_subscription_actions_nonce').val();

            button.prop('disabled', true).text(shopmetricsSettings.text_processing);
            statusSpan.text('').removeClass('notice notice-error notice-success');

            $.post(ajaxurl, {
                action: 'shopmetrics_cancel_subscription',
                _ajax_nonce: nonce
            })
            .done(function(response) {
                if (response.success) {
                    statusSpan.text(response.data.message + ' Effective: ' + response.data.cancel_at_display).addClass('notice notice-warning');
                    // Update button and status area (replace cancel button, show pending message)
                    $('#shopmetrics-subscription-management-area').html(
                        '<p class="description">' + shopmetricsSettings.text_pending_cancellation + '</p>' +
                        '<p><span class="dashicons dashicons-warning"></span> ' + shopmetricsSettings.status_pending_cancellation + '</p>' +
                        '<span class="description">' + response.data.cancel_at_display_full + '</span>' +
                        '<br><br><button id="shopmetrics-reactivate-subscription" class="button button-primary">' + shopmetricsSettings.text_reactivate_subscription + '</button>' +
                        '<span id="shopmetrics-subscription-status-message" class="notice"></span>' // Add status span back
                    );

                } else {
                    statusSpan.text(shopmetricsSettings.error_prefix + response.data.message).addClass('notice notice-error');
                    button.prop('disabled', false).text(shopmetricsSettings.text_cancel_subscription); // Re-enable button on error
                }
            })
            .fail(function(xhr) {
                const errorMsg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message 
                                 ? xhr.responseJSON.data.message 
                                 : shopmetricsSettings.error_generic;
                statusSpan.text(shopmetricsSettings.error_prefix + errorMsg).addClass('notice notice-error');
                button.prop('disabled', false).text(shopmetricsSettings.text_cancel_subscription); // Re-enable button on error
            });
        });

        // Handle 'Reactivate Subscription' button click (New)
        $(document).on('click', '#shopmetrics-reactivate-subscription', function(e) {
            e.preventDefault();
            const button = $(this);
            const statusSpan = $('#shopmetrics-reactivate-subscription-status'); // Use the correct status span id
            const nonce = button.data('nonce'); // Get nonce from button's data-nonce attribute

            // Add confirmation dialog
            if (!confirm(shopmetricsSettings.text_confirm_reactivate)) {
                return; // Abort if user cancels
            }

            button.prop('disabled', true).text(shopmetricsSettings.text_reactivating_subscription); // Use specific text
            statusSpan.text('').removeClass('notice notice-error notice-success notice-warning'); // Clear existing messages

            $.post(ajaxurl, {
                action: 'shopmetrics_reactivate_subscription',
                nonce: nonce // Correct key expected by check_ajax_referer
            })
            .done(function(response) {
                if (response.success) {
                    // statusSpan.text(response.data.message).addClass('notice notice-success'); // Status span might not exist after reload
                    // // Update button and status area (replace reactivate button/pending message with active status and cancel button)
                    // $('#shopmetrics-subscription-management-area').html(
                    //     '<p><span class="dashicons dashicons-yes-alt"></span> ' + shopmetricsSettings.status_active + '</p>' +
                    //     '<button id="shopmetrics-cancel-subscription-button" class="button">' + shopmetricsSettings.text_cancel_subscription + '</button>' +
                    //     '<span id="shopmetrics-subscription-status-message" class="notice"></span>' // Add status span back
                    // );

                    // Get the dedicated status span for reactivation
                    const reactivateStatusSpan = $('#shopmetrics-reactivate-subscription-status');
                    reactivateStatusSpan.text((response.data.message || 'Success!') + ' Reloading...').css('color', 'green');
                    
                    // Reload the page after delay
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000); // 2 second delay

                } else {
                    // statusSpan.text(shopmetricsSettings.error_prefix + response.data.message).addClass('notice notice-error');
                    // Use the dedicated status span for errors too
                    const reactivateStatusSpan = $('#shopmetrics-reactivate-subscription-status');
                    reactivateStatusSpan.text(shopmetricsSettings.error_generic + ': ' + (response.data?.message || 'Unknown error')).css('color', 'red');
                    button.prop('disabled', false).text(shopmetricsSettings.text_reactivate_subscription); // Re-enable button on error
                }
            })
            .fail(function(xhr) {
                const errorMsg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message 
                                 ? xhr.responseJSON.data.message 
                                 : shopmetricsSettings.error_generic;
                // statusSpan.text(shopmetricsSettings.error_prefix + errorMsg).addClass('notice notice-error');
                // Use the dedicated status span for AJAX errors
                 const reactivateStatusSpan = $('#shopmetrics-reactivate-subscription-status');
                 reactivateStatusSpan.text('AJAX Error: ' + errorMsg).css('color', 'red');
                button.prop('disabled', false).text(shopmetricsSettings.text_reactivate_subscription); // Re-enable button on error
            });
        });

        // --- Sync Button Handler --- 
        $('#shopmetrics-sync-button').on('click', function(e) {
            e.preventDefault();
            const $button = $(this);
            const $statusSpan = $('#shopmetrics-sync-status');

            // Use the nonce localized in shopmetricsSettings
            const nonce = shopmetricsSettings.nonce; 

            $button.prop('disabled', true);
            $statusSpan.text('Requesting sync...').css('color', 'orange');

            $.ajax({
                url: shopmetricsSettings.ajax_url, 
                type: 'POST',
                data: {
                    action: 'shopmetrics_start_sync', 
                    _ajax_nonce: nonce // Send the nonce
                    // No other data needed for this action
                },
                success: function(response) {
                    if (response.success) {
                        $statusSpan.text(response.data.message || 'Sync scheduled successfully.').css('color', 'green');
                        // Optionally keep button disabled briefly or permanently?
                        // For now, re-enable after a short delay
                        setTimeout(() => { $button.prop('disabled', false); }, 2000);
                    } else {
                        $statusSpan.text('Error: ' + (response.data.message || 'Failed to schedule sync.')).css('color', 'red');
                        $button.prop('disabled', false); // Re-enable on error
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    logger.error("AJAX Error requesting sync:", textStatus, errorThrown);
                    $statusSpan.text('AJAX Error: Could not schedule sync. Check console.').css('color', 'red');
                    $button.prop('disabled', false); // Re-enable on error
                }
            });
        });

        // --- Manual Inventory Snapshot Button Handler ---
        const manualSnapshotButton = $('#shopmetrics-manual-snapshot-button');
        const manualSnapshotStatusSpan = $('#shopmetrics-manual-snapshot-status');

        if (manualSnapshotButton.length) {
            manualSnapshotButton.on('click', function(e) {
                e.preventDefault();

                manualSnapshotButton.prop('disabled', true);
                manualSnapshotStatusSpan.text('Triggering snapshot...').css('color', 'orange');

                // Use the nonce localized in shopmetricsSettings (which corresponds to 'fm_settings_ajax_nonce' action)
                const ajaxData = {
                    action: 'shopmetrics_manual_snapshot',
                    nonce: shopmetricsSettings.nonce // shopmetricsSettings.nonce should be available from wp_localize_script
                };

                $.post(shopmetricsSettings.ajax_url, ajaxData, function(response) {
                    if (response.success) {
                        manualSnapshotStatusSpan.text(response.data.message || 'Snapshot triggered. Check logs.').css('color', 'green');
                        // Re-enable button after a delay
                        setTimeout(function() {
                            manualSnapshotButton.prop('disabled', false);
                            manualSnapshotStatusSpan.text(''); // Clear status after a bit longer
                        }, 4000); 
                    } else {
                        manualSnapshotStatusSpan.text('Error: ' + (response.data?.message || 'Unknown error')).css('color', 'red');
                        manualSnapshotButton.prop('disabled', false);
                    }
                }).fail(function(xhr, status, error) {
                    manualSnapshotStatusSpan.text('AJAX Error: ' + error).css('color', 'red');
                    manualSnapshotButton.prop('disabled', false);
                });
            });
        }

        // Manual Inventory Snapshot trigger
        $('#manual-snapshot-trigger').on('click', function(e) {
            e.preventDefault();
            const $button = $(this);
            const $status = $('#manual-snapshot-status');
            
            // Disable button and show loading
            $button.prop('disabled', true).text(shopmetricsSettings.text_processing || 'Processing...');
            $status.text('').show().html('<span class="spinner is-active" style="float:none;margin:0;"></span> Triggering snapshot...');
            
            $.ajax({
                url: shopmetricsSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'shopmetrics_manual_snapshot',
                    nonce: shopmetricsSettings.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.removeClass('shopmetrics-error-text').addClass('shopmetrics-success-text').text(response.data.message);
                    } else {
                        $status.removeClass('shopmetrics-success-text').addClass('shopmetrics-error-text').text(response.data.message || 'Error: Unknown error occurred');
                    }
                },
                error: function() {
                    $status.removeClass('shopmetrics-success-text').addClass('shopmetrics-error-text').text('Error: Server connection failed');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false).text(shopmetricsSettings.text_take_snapshot || 'Take Inventory Snapshot Now');
                    
                    // Hide status after 10 seconds
                    setTimeout(function() {
                        $status.fadeOut();
                    }, 10000);
                }
            });
        });
        
        // Fix snapshot schedule
        $('#fix-snapshot-schedule').on('click', function(e) {
            e.preventDefault();
            const $button = $(this);
            const originalText = $button.text();
            
            // Disable button and show loading
            $button.prop('disabled', true).text(shopmetricsSettings.text_processing || 'Processing...');
            
            $.ajax({
                url: shopmetricsSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'shopmetrics_fix_snapshot_schedule',
                    nonce: shopmetricsSettings.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $button.parent().prev('.shopmetrics-error-text').replaceWith(
                            '<p class="shopmetrics-success-text">' + response.data.message + '</p>'
                        );
                        if (response.data.next_snapshot) {
                            $button.parent().after(
                                '<p><strong>' + (shopmetricsSettings.text_next_snapshot || 'Next scheduled snapshot:') + '</strong> ' + 
                                response.data.next_snapshot + '</p>'
                            );
                        }
                        $button.fadeOut();
                    } else {
                        alert(response.data.message || 'Error: Unknown error occurred');
                    }
                },
                error: function() {
                    alert('Error: Server connection failed');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });

        // --- Phase 6.1.3: Enhanced Subscription Management ---
        
        // Load billing history when page loads
        if (typeof shopmetricsSettings !== 'undefined' && shopmetricsSettings.plugin_page === 'subscription') {
            loadBillingHistory();
            loadSubscriptionDetails();
        }

        // Load billing history function
        function loadBillingHistory() {
            const billingHistoryContainer = $('#shopmetrics-billing-history-container');
            if (billingHistoryContainer.length === 0) return;

            billingHistoryContainer.html('<div class="spinner-container"><span class="spinner is-active"></span> Loading billing history...</div>');

            $.ajax({
                url: shopmetricsSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'shopmetrics_get_billing_history',
                    nonce: shopmetricsSettings.nonce
                },
                success: function(response) {
                    if (response.success && response.data.history) {
                        let historyHtml = '<table class="widefat striped"><thead><tr><th>Date</th><th>Description</th><th>Amount</th><th>Status</th></tr></thead><tbody>';
                        
                        if (response.data.history.length === 0) {
                            historyHtml += '<tr><td colspan="4" style="text-align: center; padding: 20px; color: #666;">No billing history available yet.</td></tr>';
                        } else {
                            response.data.history.forEach(function(item) {
                                const statusClass = item.status === 'paid' ? 'success' : 
                                                  item.status === 'failed' ? 'error' : 'warning';
                                historyHtml += '<tr>';
                                historyHtml += '<td>' + item.date + '</td>';
                                historyHtml += '<td>' + item.description + '</td>';
                                historyHtml += '<td>' + item.amount + '</td>';
                                historyHtml += '<td><span class="status-' + statusClass + '">' + item.status + '</span></td>';
                                historyHtml += '</tr>';
                            });
                        }
                        
                        historyHtml += '</tbody></table>';
                        billingHistoryContainer.html(historyHtml);
                    } else {
                        billingHistoryContainer.html('<p class="notice notice-error">Failed to load billing history: ' + 
                                                   (response.data?.message || 'Unknown error') + '</p>');
                    }
                },
                error: function() {
                    billingHistoryContainer.html('<p class="notice notice-error">Failed to load billing history. Please try again.</p>');
                }
            });
        }

        // Load subscription details function  
        function loadSubscriptionDetails() {
            $.ajax({
                url: shopmetricsSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'shopmetrics_get_subscription_details',
                    nonce: shopmetricsSettings.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update next billing date if element exists
                        const nextBillingElement = $('#shopmetrics-next-billing-date');
                        if (nextBillingElement.length && response.data.next_billing_date) {
                            const billingDate = new Date(response.data.next_billing_date);
                            nextBillingElement.text(billingDate.toLocaleDateString());
                        }

                        // Update pricing display if element exists
                        const yearlyPriceElement = $('#shopmetrics-yearly-price');
                        const monthlyPriceElement = $('#shopmetrics-monthly-equivalent');
                        if (yearlyPriceElement.length && response.data.pricing) {
                            yearlyPriceElement.text(response.data.pricing.yearly_price || '€99');
                        }
                        if (monthlyPriceElement.length && response.data.pricing) {
                            monthlyPriceElement.text(response.data.pricing.monthly_equivalent || '€8.25');
                        }
                    }
                },
                error: function() {
                    logger.warn('Failed to load subscription details');
                }
            });
        }

        // Enhanced cancellation modal (Phase 6.1.3)
        $(document).on('click', '#shopmetrics-cancel-subscription-enhanced', function(e) {
            e.preventDefault();
            showCancellationModal();
        });

        // Show cancellation modal with reason/feedback form
        function showCancellationModal() {
            const modalHtml = `
                <div id="shopmetrics-cancellation-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999999;">
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%;">
                        <h2 style="margin-top: 0;">Cancel Subscription</h2>
                        <p>We're sorry to see you go! Please let us know why you're canceling so we can improve our service:</p>
                        
                        <form id="shopmetrics-cancellation-form">
                            <h4>Reason for cancellation:</h4>
                            <label><input type="radio" name="reason" value="too_expensive"> Too expensive</label><br>
                            <label><input type="radio" name="reason" value="not_using"> Not using the features</label><br>
                            <label><input type="radio" name="reason" value="technical_issues"> Technical issues</label><br>
                            <label><input type="radio" name="reason" value="switching_service"> Switching to another service</label><br>
                            <label><input type="radio" name="reason" value="business_closed"> Business closed/sold</label><br>
                            <label><input type="radio" name="reason" value="other"> Other</label><br><br>
                            
                            <h4>Additional feedback (optional):</h4>
                            <textarea name="feedback" rows="4" style="width: 100%; max-width: 100%;" placeholder="Please share any specific feedback that could help us improve..."></textarea><br><br>
                            
                            <p><strong>Important:</strong> Your subscription will remain active until the end of your current billing period. You will continue to have access to all premium features until then.</p>
                            
                            <div style="text-align: right; margin-top: 20px;">
                                <button type="button" id="shopmetrics-modal-cancel" class="button" style="margin-right: 10px;">Keep Subscription</button>
                                <button type="submit" id="shopmetrics-modal-confirm" class="button button-primary">Confirm Cancellation</button>
                            </div>
                            
                            <div id="shopmetrics-cancellation-status" style="margin-top: 15px; text-align: center;"></div>
                        </form>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            
            // Handle modal close
            $('#shopmetrics-modal-cancel').on('click', function() {
                $('#shopmetrics-cancellation-modal').remove();
            });
            
            // Handle form submission
            $('#shopmetrics-cancellation-form').on('submit', function(e) {
                e.preventDefault();
                processCancellation();
            });
        }

        // Process cancellation with reason and feedback
        function processCancellation() {
            const form = $('#shopmetrics-cancellation-form');
            const reason = form.find('input[name="reason"]:checked').val();
            const feedback = form.find('textarea[name="feedback"]').val();
            const statusDiv = $('#shopmetrics-cancellation-status');
            const submitBtn = $('#shopmetrics-modal-confirm');
            
            // Show loading state
            submitBtn.prop('disabled', true).text('Processing...');
            statusDiv.html('<span class="spinner is-active"></span> Processing cancellation...');
            
            $.ajax({
                url: shopmetricsSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'shopmetrics_cancel_subscription',
                    nonce: shopmetricsSettings.nonce,
                    reason: reason || '',
                    feedback: feedback || ''
                },
                success: function(response) {
                    if (response.success) {
                        statusDiv.html('<div style="color: green; font-weight: bold;">✓ ' + response.data.message + '</div>');
                        setTimeout(function() {
                            $('#shopmetrics-cancellation-modal').remove();
                            window.location.reload();
                        }, 2000);
                    } else {
                        statusDiv.html('<div style="color: red;">Error: ' + (response.data?.message || 'Unknown error') + '</div>');
                        submitBtn.prop('disabled', false).text('Confirm Cancellation');
                    }
                },
                error: function() {
                    statusDiv.html('<div style="color: red;">Connection error. Please try again.</div>');
                    submitBtn.prop('disabled', false).text('Confirm Cancellation');
                }
            });
        }

        // Close modal when clicking outside
        $(document).on('click', '#shopmetrics-cancellation-modal', function(e) {
            if (e.target.id === 'shopmetrics-cancellation-modal') {
                $(this).remove();
            }
        });
        
    });

})(jQuery); 