(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 */

	// Ensure logger is defined before any usage
	window.logger = {
		log: (...args) => {
			if (typeof shopmetricsanalytics_admin_params === 'undefined' || shopmetricsanalytics_admin_params.debugLogging) {
				console.log(...args);
			}
		},
		warn: (...args) => {
			if (typeof shopmetricsanalytics_admin_params === 'undefined' || shopmetricsanalytics_admin_params.debugLogging) {
				console.warn(...args);
			}
		},
		error: (...args) => {
			if (typeof shopmetricsanalytics_admin_params === 'undefined' || shopmetricsanalytics_admin_params.debugLogging) {
				console.error(...args);
			}
		}
	};

	// Debug mode - set to false to disable verbose logging in production
	const DEBUG = false;

	// Helper function to log only when debug is enabled
	function debugLog(...args) {
		if (DEBUG) {
			console.log(...args);
		}
	}

	// Patch jQuery's insertBefore method to prevent circular reference errors
	(function patchjQuery() {
		// Store the original insertBefore method
		var originalInsertBefore = $.fn.insertBefore;
		
		// Override with a version that checks for circular references
		$.fn.insertBefore = function(target) {
			var $target = $(target);
			var $this = this;
			
			// Check if this would create a circular reference
			// (i.e., trying to insert an element before itself or one of its ancestors)
			var wouldCreateCircular = false;
			$this.each(function() {
				var elem = this;
				if ($target.filter(function() { 
					return this === elem || $.contains(elem, this); 
				}).length > 0) {
					wouldCreateCircular = true;
					return false; // Break the each loop
				}
			});
			
			// If it would create a circular reference, log and return without performing the operation
			if (wouldCreateCircular) {
				logger.log('Prevented circular DOM reference in insertBefore');
				return $this; // Return the jQuery object to maintain chainability
			}
			
			// Otherwise, call the original method
			return originalInsertBefore.apply(this, arguments);
		};
	})();

	// Global error handler for DOM manipulation errors
	window.addEventListener('error', function(event) {
		if (event.error && event.error.name === 'HierarchyRequestError') {
			logger.log('DOM Hierarchy Error caught:', event.error.message);
			if (DEBUG) {
				logger.log('Error occurred at:', event.filename, 'line:', event.lineno, 'column:', event.colno);
			}
			// Prevent the error from being displayed in the console
			event.preventDefault();
		}
	});

	$(document).ready(function() {
		// Add monitoring for DOM mutations that might cause issues - only in debug mode
		if (window.MutationObserver && DEBUG) {
			var observer = new MutationObserver(function(mutations) {
				mutations.forEach(function(mutation) {
					if (mutation.type === 'childList' && mutation.addedNodes.length) {
						for (var i = 0; i < mutation.addedNodes.length; i++) {
							var node = mutation.addedNodes[i];
							if (node.nodeType === 1 && node.classList && 
								(node.classList.contains('shopmetrics-section-header') || 
								 node.classList.contains('shopmetrics-settings-section-title'))) {
								logger.log('Section DOM modified');
							}
						}
					}
				});
			});
			
			// Start observing the entire document
			observer.observe(document.body, {
				childList: true,
				subtree: true
			});
			
			logger.log('DOM mutation observer started');
		}
		
		// COGS Meta Key Detection functionality
		initCogsMetaKeyDetection();

		// Cart Recovery Email Test functionality
		initCartRecoveryEmailTest();

		// Snapshot schedule fix
		initFixSnapshotSchedule();

		// Manual snapshot trigger
		initManualSnapshotTrigger();

		// Order blocks feature removed - using flat pricing model

		// Historical sync progress tracking
		initHistoricalSyncProgress();

		// Diagnostic function to help identify what's causing the error - only in debug mode
		if (DEBUG) {
			diagnoseErrors();
		}
	});

	/**
	 * Initialize the COGS Meta Key detection functionality
	 */
	function initCogsMetaKeyDetection() {
		// Auto-detect COGS meta key button
		$('#shopmetrics_auto_detect_cogs_key_button').on('click', function(e) {
			e.preventDefault();
			
			        const resultArea = $('#shopmetrics_cogs_detection_result_area');
			resultArea.html('<p>' + shopmetricsanalytics_admin_params.i18n.detecting + '</p>');
			resultArea.show();
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'shopmetrics_auto_detect_cogs_key',
					nonce: shopmetricsanalytics_admin_params.settings_nonce
				},
				success: function(response) {
					if (response.success) {
						resultArea.html('<p>' + response.data.message + '</p>');
						
						if (response.data.detected_key) {
							resultArea.append('<p><button type="button" class="button button-primary shopmetrics-use-detected-key" data-key="' + 
								response.data.detected_key + '">' + shopmetricsanalytics_admin_params.i18n.use_this_key + '</button></p>');
						}
					} else {
						resultArea.html('<p class="shopmetrics-error-text">' + (response.data ? response.data.message : shopmetricsanalytics_admin_params.i18n.error) + '</p>');
					}
				},
				error: function() {
					resultArea.html('<p class="shopmetrics-error-text">' + shopmetricsanalytics_admin_params.i18n.ajax_error + '</p>');
				}
			});
		});
		
		// Manual select COGS meta key button
		$('#shopmetrics_manual_select_cogs_key_button').on('click', function(e) {
			e.preventDefault();
			
			const manualSelectArea = $('#shopmetrics_cogs_manual_select_area');
			
			if (manualSelectArea.is(':visible')) {
				manualSelectArea.hide();
				return;
			}
			
			manualSelectArea.show();
			const dropdown = $('#shopmetrics_cogs_meta_key_dropdown');
			
			// If dropdown is already populated, no need to fetch again
			if (dropdown.find('option').length > 2) {
				return;
			}
			
			// Show loading indicator
			dropdown.html('<option value="">' + shopmetricsanalytics_admin_params.i18n.loading + '</option>');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'shopmetrics_get_all_meta_keys',
					nonce: shopmetricsanalytics_admin_params.settings_nonce
				},
				success: function(response) {
					if (response.success && response.data.meta_keys) {
						dropdown.empty();
						$.each(response.data.meta_keys, function(i, option) {
							dropdown.append($('<option></option>')
								.attr('value', option.value)
								.text(option.label));
						});
					} else {
						dropdown.html('<option value="">' + shopmetricsanalytics_admin_params.i18n.error_loading + '</option>');
					}
				},
				error: function() {
					dropdown.html('<option value="">' + shopmetricsanalytics_admin_params.i18n.ajax_error + '</option>');
				}
			});
		});
		
		// Handle selecting key from dropdown
		$(document).on('change', '#shopmetrics_cogs_meta_key_dropdown', function() {
			const selectedValue = $(this).val();
			
			// Skip the placeholder option
			if (selectedValue === '_placeholder_') {
				return;
			}
			
			// Update the hidden input and display
			$('#shopmetrics_settings_cogs_meta_key_hidden_input').val(selectedValue);
			
			if (selectedValue) {
				$('#shopmetrics_current_cogs_key_display').text(selectedValue);
			} else {
				$('#shopmetrics_current_cogs_key_display').text(shopmetricsanalytics_admin_params.i18n.not_set);
			}
		});
		
		// Handle clicking "Use this key" button
		$(document).on('click', '.shopmetrics-use-detected-key', function() {
			const key = $(this).data('key');
			
			// Update the hidden input and display
			$('#shopmetrics_settings_cogs_meta_key_hidden_input').val(key);
			$('#shopmetrics_current_cogs_key_display').text(key);
			
			// Show success message
			            $('#shopmetrics_cogs_detection_result_area').html(
				'<p class="shopmetrics-success-text">' + shopmetricsanalytics_admin_params.i18n.key_selected.replace('%s', key) + '</p>'
			);
			
			// If manual dropdown is visible, update it too
			if ($('#shopmetrics_cogs_manual_select_area').is(':visible')) {
				const dropdown = $('#shopmetrics_cogs_meta_key_dropdown');
				if (dropdown.find('option[value="' + key + '"]').length) {
					dropdown.val(key);
				}
			}
		});
	}

	/**
	 * Initialize the Cart Recovery email test functionality
	 */
	function initCartRecoveryEmailTest() {
		// Send test email button
		$('#send_test_email').on('click', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var originalText = $button.text();
			var email = $('#shopmetrics_cart_recovery_test_email').val();
			
			if (!email) {
				alert('Please enter a test email address.');
				return;
			}

			$button.text(shopmetricsanalytics_admin_params.i18n.sending).prop('disabled', true);
			
			$.ajax({
				url: shopmetricsanalytics_admin_params.ajax_url,
				type: 'POST',
				data: {
					action: 'shopmetrics_test_recovery_email',
					email: email,
					nonce: shopmetricsanalytics_admin_params.cart_recovery_nonce
				},
				success: function(response) {
					if (response.success) {
						alert(shopmetricsanalytics_admin_params.i18n.email_sent);
					} else {
						$button.attr('disabled', false).text(originalText);
					}
				},
				error: function() {
					$button.attr('disabled', false).text(originalText);
				}
			});
		});
	}

	/**
	 * Initialize the snapshot schedule fix functionality
	 */
	function initFixSnapshotSchedule() {
		// Fix snapshot schedule button
		$('#fix-snapshot-schedule').on('click', function(e) {
			e.preventDefault();
			
			const $button = $(this);
			const originalText = $button.text();
			
			// Disable button and show loading text
			$button.attr('disabled', true).text('...');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'shopmetrics_fix_snapshot_schedule',
					nonce: shopmetricsanalytics_admin_params.settings_nonce
				},
				success: function(response) {
					if (response.success) {
						alert(shopmetricsanalytics_admin_params.i18n.schedule_fixed);
						// Reload the page to show updated schedule info
						location.reload();
					} else {
						alert(shopmetricsanalytics_admin_params.i18n.schedule_error + ' ' + (response.data ? response.data.message : ''));
						// Re-enable button and restore text
						$button.attr('disabled', false).text(originalText);
					}
				},
				error: function() {
					alert(shopmetricsanalytics_admin_params.i18n.ajax_error);
					$button.attr('disabled', false).text(originalText);
				}
			});
		});
	}

	/**
	 * Initialize the manual snapshot trigger functionality
	 */
	function initManualSnapshotTrigger() {
		// Manual snapshot trigger button
		$('#manual-snapshot-trigger').on('click', function(e) {
			e.preventDefault();
			
			const $button = $(this);
			const originalText = $button.text();
			const $status = $('#manual-snapshot-status');
			
			// Disable button and show loading text
			$button.attr('disabled', true).text('...');
			$status.text('').show();
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'shopmetrics_manual_snapshot',
					nonce: shopmetricsanalytics_admin_params.settings_nonce
				},
				success: function(response) {
					if (response.success) {
						$status.text(shopmetricsanalytics_admin_params.i18n.snapshot_initiated).css('color', 'green');
					} else {
						$status.text(shopmetricsanalytics_admin_params.i18n.snapshot_error + ' ' + (response.data ? response.data.message : '')).css('color', 'red');
					}
					// Re-enable button and restore text
					$button.attr('disabled', false).text(originalText);
				},
				error: function() {
					$status.text(shopmetricsanalytics_admin_params.i18n.ajax_error).css('color', 'red');
					$button.attr('disabled', false).text(originalText);
				}
			});
		});
	}

	// Order blocks functionality removed - using flat pricing model

	/**
	 * Initialize historical sync progress tracking and UI
	 */
	function initHistoricalSyncProgress() {
		const $syncButton = $('#shopmetrics_start_sync');
		if ($syncButton.length === 0) {
			return;
		}

		// Add reset sync button directly after the sync button (initially hidden)
		$syncButton.after('<button id="shopmetrics-reset-sync-button" class="button" style="margin-left: 10px; display: none;">Reset Sync</button>');
		const $resetButton = $('#shopmetrics-reset-sync-button');

		// Set up the reset button click handler
		$resetButton.on('click', function(e) {
			e.preventDefault();
			
			// Check if shift key is pressed for force reset
			var isForceReset = e.shiftKey;
			var confirmMessage = isForceReset 
				? 'Are you sure you want to FORCE reset the sync progress? This will clear ALL sync flags and should only be used when troubleshooting.' 
				: 'Are you sure you want to reset the sync progress? This will allow you to start a new sync.';
			
			if (!confirm(confirmMessage)) {
				return;
			}

			// Save original button text
			const originalButtonText = $resetButton.text();

			// Disable the button during the request
			$resetButton.prop('disabled', true).text('Resetting...');

			// Get nonce, preferring sync-specific nonce if available
			const nonce = $syncButton.data('sync-nonce') || $syncButton.data('nonce');
			
			// Make AJAX request to reset the sync
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'shopmetrics_reset_sync_progress',
					_ajax_nonce: nonce,
					force_reset: isForceReset
				},
				success: function(response) {
					if (response.success) {
						alert('Sync progress reset successfully. You can now start a new sync.' + (isForceReset ? ' (Force reset completed)' : ''));
						// Hide progress UI, show sync button, hide reset button
						$('.shopmetrics-sync-progress-container').hide();
						$syncButton.prop('disabled', false).show();
						$resetButton.prop('disabled', false).text(originalButtonText).hide();
						// Also hide last sync date
						$('.shopmetrics-last-sync-date').hide();
					} else {
						var errorMessage = response.data && response.data.message ? response.data.message : 'Unknown error';
						if (errorMessage.includes('in progress') && !isForceReset) {
							errorMessage += '\n\nTip: Hold Shift and click "Reset Sync" to force reset a stuck sync.';
						}
						alert('Error: ' + errorMessage);
						$resetButton.prop('disabled', false).text(originalButtonText);
					}
				},
				error: function() {
					alert('Error communicating with the server. Please try again.');
					$resetButton.prop('disabled', false).text(originalButtonText);
				},
				complete: function() {
					// Make absolutely sure the button is reset in all cases
					if ($resetButton.text() === 'Resetting...') {
						$resetButton.prop('disabled', false).text(originalButtonText);
					}
				}
			});
		});

		// Check initial progress status immediately
		checkSyncProgress(true);

		// On sync button click
		$syncButton.on('click', function(e) {
			e.preventDefault();
			
			// Disable the button during the request
			$syncButton.prop('disabled', true);
			
			// Hide the last sync date during new sync
			$('.shopmetrics-last-sync-date').hide();
			
			// Show progress UI immediately with initial 0% state
			showProgressUI({
				status: 'starting',
				progress: 0,
				processed_orders: 0,
				total_orders: 'calculating...'
			});
			
			// Show reset button immediately
			$resetButton.show();
			
			// Get nonce, preferring sync-specific nonce if available
			const nonce = $syncButton.data('sync-nonce') || $syncButton.data('nonce');
			
			// Make AJAX request
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'shopmetrics_start_sync',
					_ajax_nonce: nonce
				},
				success: function(response) {
					logger.log('Starting sync, response:', response);
					
					if (response.success) {
						// Update progress UI with response data
						updateProgressUI(response.data.progress);
						// Start checking progress
						setTimeout(checkSyncProgress, 1000);
					} else {
						// Show error message
						$syncButton.prop('disabled', false);
						alert('AJAX Error: ' + (response.data ? response.data.message : 'Could not schedule sync. Check console.'));
						
						// If the error is that sync is already in progress, update progress UI
						if (response.data && response.data.progress) {
							updateProgressUI(response.data.progress);
							// Keep sync button disabled
							$syncButton.prop('disabled', true);
							// Start checking progress
							setTimeout(checkSyncProgress, 1000);
						} else {
							// Hide progress UI if no sync is in progress
							hideProgressUI();
						}
					}
				},
				error: function(xhr, status, error) {
					logger.error('Error starting sync:', error);
					$syncButton.prop('disabled', false);
					// Hide progress UI on error
					hideProgressUI();
					// Hide reset button on error
					$resetButton.hide();
					alert('AJAX Error: Could not schedule sync. Check console.');
				}
			});
		});

		/**
		 * Check the current sync progress via AJAX
		 * @param {boolean} isInitialCheck - Whether this is the initial check on page load
		 */
		function checkSyncProgress(isInitialCheck = false) {
			logger.log('ShopMetrics: Checking sync progress...');
			
			// Get nonce, preferring sync-specific nonce if available
			const nonce = $syncButton.data('sync-nonce') || $syncButton.data('nonce');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'shopmetrics_get_sync_progress',
					_ajax_nonce: nonce
				},
				success: function(response) {
					logger.log('ShopMetrics: Sync progress response:', response);
					if (response.success && response.data) {
						// For initial check, only show UI if sync was in progress
						if (isInitialCheck) {
							if (response.data.status === 'in_progress' || response.data.status === 'starting' || 
							    response.data.status === 'stalled' || response.data.status === 'error') {
								showProgressUI(response.data);
							} else if (response.data.status === 'completed' || parseInt(response.data.progress) === 100) {
								// If sync is completed on initial load, don't show progress UI, just show last sync date
								showLastSyncDate(response.data.last_synced_date);
								$resetButton.hide();
							}
						} else {
							// Normal update during active monitoring
							showProgressUI(response.data);
						}
						
						updateProgressUI(response.data);
						
						// Always show reset button when any sync status is detected
						if (response.data.status === 'completed' || parseInt(response.data.progress) === 100) {
							// Hide reset button when sync is complete
							$resetButton.hide();
							
							// If this is during active sync monitoring, hide progress UI after a delay
							if (!isInitialCheck) {
								setTimeout(function() {
									hideProgressUI();
									showLastSyncDate(response.data.last_synced_date);
								}, 3000);
							}
						} else if (!isInitialCheck) {
							// Only show reset button for non-completed states during normal operation (not initial check)
							$resetButton.show();
						}
						
						// Keep sync button disabled during in-progress and starting states
						if (response.data.status === 'in_progress' || response.data.status === 'starting') {
							$syncButton.prop('disabled', true);
							setTimeout(checkSyncProgress, 1000);
						} else if (response.data.status === 'completed') {
							// If completed, re-enable the sync button after a delay
							setTimeout(function() {
								$syncButton.prop('disabled', false);
							}, 5000);
						} else if (response.data.status === 'stalled' || response.data.status === 'error') {
							// For stalled or error status, keep reset button visible
							$resetButton.show();
							// But enable sync button in case user wants to try again
							$syncButton.prop('disabled', false);
						}
					} else {
						logger.warn('ShopMetrics: Invalid progress response:', response);
						// Re-enable the sync button
						$syncButton.prop('disabled', false);
					}
				},
				error: function() {
					logger.error('ShopMetrics: Error checking sync progress');
					// Re-enable the sync button
					$syncButton.prop('disabled', false);
				}
			});
		}

		/**
		 * Show the last sync date below the sync button
		 */
		function showLastSyncDate(lastSyncDate) {
			if (!lastSyncDate) return;
			
			// Check if the element already exists
			let $lastSyncElement = $('.shopmetrics-last-sync-date');
			if ($lastSyncElement.length === 0) {
				// Create and position the element after the sync button
				$resetButton.after('<div class="shopmetrics-last-sync-date" style="margin-top: 10px; color: #666; font-style: italic;"></div>');
				$lastSyncElement = $('.shopmetrics-last-sync-date');
			}
			
			// Update the text and make sure it's visible
			$lastSyncElement.text(shopmetricsL10n.lastSynchronization + ': ' + lastSyncDate).show();
		}

		/**
		 * Hide the progress UI
		 */
		function hideProgressUI() {
			$('.shopmetrics-sync-progress-container').hide();
		}

		/**
		 * Show the progress UI
		 */
		function showProgressUI(initialData) {
			// Create or locate the progress container
			let $progressContainer = $('.shopmetrics-sync-progress-container');
			if ($progressContainer.length === 0) {
				// Create progress container AFTER buttons, not between them
				// Check if there's already a last sync date element
				let $lastSyncElement = $('.shopmetrics-last-sync-date');
				
				// If last sync date exists, insert before it, otherwise after reset button
				if ($lastSyncElement.length > 0) {
					$lastSyncElement.before('<div class="shopmetrics-sync-progress-container" style="margin-top: 15px; margin-bottom: 15px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;"></div>');
				} else {
					$resetButton.after('<div class="shopmetrics-sync-progress-container" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;"></div>');
				}
				
				$progressContainer = $('.shopmetrics-sync-progress-container');
				
				// Add structure for progress bar and details
				const progressHTML = `
					<div class="shopmetrics-progress-wrapper">
						<div class="shopmetrics-progress-info">
							<span class="shopmetrics-progress-status">Initializing...</span>
							<span class="shopmetrics-progress-percentage">0%</span>
						</div>
						<div class="shopmetrics-progress-bar-container" style="height: 20px; background-color: #f0f0f0; border-radius: 3px; overflow: hidden;">
							<div class="shopmetrics-progress-bar" style="height: 100%; background-color: #2271b1; width: 0%; transition: width 0.5s ease;"></div>
						</div>
						<div class="shopmetrics-progress-details" style="display: flex; justify-content: space-between; margin-top: 5px; font-size: 0.9em; color: #666;">
							<span class="shopmetrics-last-synced-date"></span>
							<span class="shopmetrics-progress-count"></span>
						</div>
					</div>
				`;
				
				$progressContainer.html(progressHTML);
			}
			
			// Update with initial data if available
			if (initialData) {
				updateProgressUI(initialData);
			}
			
			// Show the progress container
			$progressContainer.show();
		}

		/**
		 * Update the progress UI with the latest data
		 */
		function updateProgressUI(data) {
			if (!data) return;
			
			const $container = $('.shopmetrics-sync-progress-container');
			if ($container.length === 0) return;
			
			const $status = $container.find('.shopmetrics-progress-status');
			const $percentage = $container.find('.shopmetrics-progress-percentage');
			const $bar = $container.find('.shopmetrics-progress-bar');
			const $lastSyncedDate = $container.find('.shopmetrics-last-synced-date');
			const $progressCount = $container.find('.shopmetrics-progress-count');
			
			// Update status message
			let statusText = '';
			switch (data.status) {
				case 'starting':
					statusText = 'Starting synchronization...';
					break;
				case 'in_progress':
					statusText = 'Synchronization in progress';
					break;
				case 'completed':
					statusText = 'Synchronization completed';
					break;
				case 'error':
					statusText = 'Error: ' + (data.message || 'Unknown error');
					break;
				case 'stalled':
					statusText = 'Synchronization stalled. Please reset and try again.';
					break;
				default:
					statusText = 'Status: ' + data.status;
			}
			
			// If there's an error message, append it to the status
			if (data.last_error) {
				statusText += ' (Error: ' + data.last_error + ')';
			}
			
			$status.text(statusText);
			
			// Update percentage
			const progress = parseInt(data.progress) || 0;
			$percentage.text(progress + '%');
			$bar.css('width', progress + '%');
			
			// Update count of processed items
			if (data.processed_orders !== undefined && data.total_orders !== undefined) {
				$progressCount.text('Processed ' + data.processed_orders + ' of ' + data.total_orders + ' orders');
			}
			
			// Update last synced date if available
			if (data.last_synced_date) {
				$lastSyncedDate.text('Last synced: ' + data.last_synced_date);
			} else {
				$lastSyncedDate.text('');
			}
			
			// Hide or show reset button based on status and progress
			if (data.status === 'completed' || progress === 100) {
				// Hide reset button when sync is complete
				$resetButton.hide();
			} else {
				// Show reset button for all other sync states
				$resetButton.show();
			}
			
			// Keep the sync button disabled during active sync
			if (data.status === 'in_progress' || data.status === 'starting') {
				$syncButton.prop('disabled', true);
			}
		}
	}

	// Run fixes again after window is fully loaded
	window.addEventListener('load', function() {
		logger.log('Window fully loaded - applying final DOM fixes');
		
		// Wait a bit more to ensure any WP admin UI JS has finished
		setTimeout(function() {
			try {
				// Final aggressive DOM structure correction
				$('.shopmetrics-settings-section').each(function() {
					var $section = $(this);
					
					// Get text content to preserve
					var headerText = $section.find('.shopmetrics-settings-section-title').first().text() || '';
					var logoHtml = $section.find('.shopmetrics-section-logo').html() || '';
					
					// Remove all header and title elements completely
					$section.find('.shopmetrics-section-header, .shopmetrics-settings-section-title').remove();
					
					// Add fresh structure at the beginning of the section
					var newStructure = 
						'<div class="shopmetrics-section-header">' +
							'<div class="shopmetrics-section-logo">' + logoHtml + '</div>' +
						'</div>' +
						'<h2 class="shopmetrics-settings-section-title">' + headerText + '</h2>';
					
					$section.prepend(newStructure);
				});
				
				logger.log('Final DOM fixes applied');
			} catch (error) {
				logger.error('Error during final DOM fixes:', error);
			}
		}, 500);
	});

	// Diagnostic function to help identify what's causing the error
	function diagnoseErrors() {
		logger.log('Starting DOM Error Diagnostics...');
		
		// Check for problematic DOM nesting
		$('.shopmetrics-settings-section-title').each(function(index) {
			var $title = $(this);
			var id = 'title-' + index;
			$title.attr('data-id', id);
			
			// Check if the title is inside a header
			if ($title.parents('.shopmetrics-section-header').length > 0) {
				logger.error('Title with DOM ID ' + id + ' is incorrectly nested inside shopmetrics-section-header');
			}
			
			// Check if the title contains its parent
			var $parent = $title.parent();
			if ($title.find($parent).length > 0) {
				logger.error('Title with DOM ID ' + id + ' incorrectly contains its parent');
			}
		});
		
		// Check if any element contains itself (circular reference)
		$('div, h1, h2, h3, h4, h5, h6, section').each(function(index) {
			var $elem = $(this);
			var findResult = $elem.find($elem);
			if (findResult.length > 0) {
				logger.error('Element contains itself (circular reference): ', $elem[0]);
			}
		});
		
		logger.log('DOM Error Diagnostics Completed');
	}

})( jQuery ); 