// Debug script to test PostHog in WordPress admin
// Add this to browser console to test PostHog functionality

console.log('=== PostHog Debug Script ===');

// Check if fmData is available
if (typeof window.fmData !== 'undefined') {
    console.log('✅ fmData is available:', window.fmData);
    
    // Check settings
    if (window.fmData.settings) {
        console.log('✅ Settings available:', window.fmData.settings);
        console.log('Analytics consent:', window.fmData.settings.analytics_consent);
    } else {
        console.log('❌ Settings not found in fmData');
    }
    
    // Check siteInfo
    if (window.fmData.siteInfo) {
        console.log('✅ Site info available:', window.fmData.siteInfo);
    } else {
        console.log('❌ Site info not found in fmData');
    }
    
    // Check debug flag
    console.log('Debug flag:', window.fmData.debug);
    
} else {
    console.log('❌ fmData is not available');
}

// Check if PostHog is loaded
if (typeof window.posthog !== 'undefined') {
    console.log('✅ PostHog is available');
    
    // Check if PostHog is initialized
    if (window.posthog.__loaded) {
        console.log('✅ PostHog is initialized');
        
        // Test event
        console.log('Sending test event...');
        window.posthog.capture('debug_test_event', {
            test: true,
            timestamp: new Date().toISOString(),
            source: 'debug_script'
        });
        console.log('✅ Test event sent');
        
    } else {
        console.log('⚠️ PostHog is loaded but not initialized');
    }
} else {
    console.log('❌ PostHog is not available');
}

// Check environment variables (if available in build)
console.log('Environment check:');
console.log('REACT_APP_POSTHOG_KEY:', process?.env?.REACT_APP_POSTHOG_KEY || 'Not available in runtime');
console.log('REACT_APP_POSTHOG_HOST:', process?.env?.REACT_APP_POSTHOG_HOST || 'Not available in runtime');

// Check if useAnalytics hook is working
if (typeof window.React !== 'undefined') {
    console.log('✅ React is available');
} else {
    console.log('❌ React is not available');
}

// Network monitoring
console.log('Monitoring network requests to PostHog...');
const originalFetch = window.fetch;
window.fetch = function(...args) {
    const url = args[0];
    if (typeof url === 'string' && url.includes('posthog.com')) {
        console.log('🌐 PostHog request:', url);
    }
    return originalFetch.apply(this, args);
};

console.log('=== Debug script complete ==='); 