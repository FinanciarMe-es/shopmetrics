jQuery(document).ready(function($) {
    const nonce = shopmetricsTestAnalytics.nonce;
    const ajaxUrl = shopmetricsTestAnalytics.ajaxUrl;

    function showResults(message, isSuccess = true) {
        $('#test-results').show();
        const color = isSuccess ? '#4CAF50' : '#f44336';
        $('#results-content').append(
            '<div style="color: ' + color + '; margin: 5px 0;">' +
            '[' + new Date().toLocaleTimeString() + '] ' + message +
            '</div>'
        );
    }

    $('#test-single-error').click(function() {
        showResults('Sending test PHP error...', true);
        $.post(ajaxUrl, {
            action: 'shopmetrics_test_php_error',
            nonce: nonce
        }, function(response) {
            if (response.success) {
                showResults('✅ Test PHP error sent successfully!', true);
            } else {
                showResults('❌ Test failed: ' + response.data.message, false);
            }
        }).fail(function() {
            showResults('❌ AJAX request failed', false);
        });
    });

    $('#test-error-scenarios').click(function() {
        showResults('Testing multiple PHP error scenarios...', true);
        $.post(ajaxUrl, {
            action: 'shopmetrics_test_error_scenarios',
            nonce: nonce
        }, function(response) {
            if (response.success) {
                showResults('✅ Multiple error scenarios tested successfully!', true);
                if (response.data.results) {
                    Object.keys(response.data.results).forEach(function(scenario) {
                        const result = response.data.results[scenario];
                        showResults('  - ' + scenario + ': ' + (result ? '✅ sent' : '❌ failed'), result);
                    });
                }
            } else {
                showResults('❌ Test failed: ' + response.data.message, false);
            }
        }).fail(function() {
            showResults('❌ AJAX request failed', false);
        });
    });
}); 