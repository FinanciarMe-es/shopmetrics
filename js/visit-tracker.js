// ShopMetrics Analytics - Visit Tracker (WP AJAX Version)

(function() {
    'use strict';
    
    // --- Logger Setup ---
    const logger = {
        log: function(...args) {
            const config = window.shopmetricsVisitTrackerData || {};
            if (config.debugLogging) {
                console.log('[ShopMetrics]', ...args);
            }
        },
        error: function(...args) {
            const config = window.shopmetricsVisitTrackerData || {};
            if (config.debugLogging) {
                console.error('[ShopMetrics]', ...args);
            }
        }
    };
    
    logger.log('ShopMetrics Visit Tracker script loaded', new Date().toISOString());

    // --- Configuration (Passed from wp_localize_script) ---
    // Expects a global object like shopmetricsVisitTrackerData with ajaxUrl and nonce
    const config = window.shopmetricsVisitTrackerData || {};
    const ajaxUrl = config.ajaxUrl; // e.g., http://my-site.com/wp-admin/admin-ajax.php
    const nonce = config.nonce;   // Security nonce
    const pageType = config.pageType || 'unknown'; // <-- Get pageType from localized data
    // Note: siteIdentifier is no longer needed here, PHP will handle it.

    // --- Helper Functions --- 

    // Function to set a cookie
    function setCookie(name, value, days) {
        let expires = "";
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        // Use encodeURIComponent to handle special characters in value
        document.cookie = name + "=" + (encodeURIComponent(value) || "") + expires + "; path=/; SameSite=Lax"; 
    }

    // Function to get a cookie
    function getCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for(let i=0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) == ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) == 0) {
                // Use decodeURIComponent to correctly read encoded values
                return decodeURIComponent(c.substring(nameEQ.length, c.length));
            }
        }
        return null;
    }

    // Function to parse UTM parameters from the current URL
    function getUtmParams() {
        const params = new URLSearchParams(window.location.search);
        const utm = {};
        const utmKeys = ['source', 'medium', 'campaign', 'term', 'content'];
        utmKeys.forEach(key => {
            const value = params.get(`utm_${key}`);
            if (value) {
                utm[key] = value;
            }
        });
        return utm;
    }

    // Function to get or generate a session ID (simple example using sessionStorage)
    function getSessionId() {
        const storageKey = 'shopmetrics_session_id';
        let sessionId = sessionStorage.getItem(storageKey);
        if (!sessionId) {
            // Generate a simple random ID (consider a more robust UUID library if needed)
            sessionId = Date.now().toString(36) + Math.random().toString(36).substring(2);
            sessionStorage.setItem(storageKey, sessionId);
        }
        return sessionId;
    }

    // Function to send the tracking data via WP AJAX
    async function trackPageView() {
        // Use the global config defined at the top
        // Check if ajaxUrl exists, if not try ajax_url (handling potential naming inconsistency)
        const ajaxUrl = config.ajaxUrl || config.ajax_url; 
        
        logger.log('ShopMetrics Analytics: trackPageView called', { 
            ajaxUrl,
            nonce,
            pageType,
            config: window.shopmetricsVisitTrackerData
        });
            
        if (!ajaxUrl || !nonce) {
            logger.log('ShopMetrics Analytics: Missing AJAX configuration for visit tracking.');
            return;
        }

        // Prepare data for WP AJAX
        const data = new URLSearchParams();
        data.append('action', 'shopmetrics_track_visit'); // Updated to match PHP handler
        data.append('nonce', nonce);            // Security nonce
        data.append('pageUrl', document.location.href); // Use camelCase
        data.append('referrer', document.referrer || ''); // Send empty string if null
        data.append('sessionId', getSessionId());      // Use camelCase
        data.append('pageType', pageType); // <-- Add pageType to payload
        
        // --- Add orderId if available (from localized data) ---
        if (config.orderId) {
            data.append('orderId', config.orderId);
            logger.log('ShopMetrics Analytics: Tracking orderId:', config.orderId);
        }
        // --- End Add ---
        
        // --- Add conversion tracking for order received page ---
        if (pageType === 'order_received' && config.orderId) {
            data.append('conversion', '1');
            data.append('conversionType', 'purchase');
            logger.log('ShopMetrics Analytics: Tracking conversion for order:', config.orderId);
        }
        // --- End conversion tracking ---
        
        // --- Add cart event tracking ---
        if (pageType === 'cart') {
            data.append('cart_event', '1');
            data.append('cart_event_type', 'view_cart');
        } else if (pageType === 'checkout') {
            data.append('cart_event', '1');
            data.append('cart_event_type', 'start_checkout');
        }
        // --- End cart event tracking ---

        // --- Read UTMs from Cookies and append to payload ---
        const utmKeys = ['source', 'medium', 'campaign', 'term', 'content'];
        const utmsFromCookies = {};
        utmKeys.forEach(key => {
            const cookieValue = getCookie('shopmetrics_utm_' + key);
            if (cookieValue) {
                utmsFromCookies[key] = cookieValue;
                // Append to AJAX data with the correct case for PHP $_POST keys
                const postKey = 'utm' + key.charAt(0).toUpperCase() + key.slice(1);
                data.append(postKey, cookieValue);
            }
        });
        logger.log('ShopMetrics Analytics: Sending UTMs from cookies:', utmsFromCookies);
        // --- End Read/Append UTMs ---

        // console.log('ShopMetrics Analytics: Tracking visit via WP AJAX', Object.fromEntries(data)); // Keep this commented out or remove for production

        try {
            // Use fetch to call admin-ajax.php
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    // Standard header for form data
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: data.toString(), // Send data as URL-encoded string
                keepalive: true 
            });

            if (!response.ok) {
                 logger.log(`ShopMetrics Analytics: WP AJAX request failed with status ${response.status}`);
            } else {
                // Check response from WP AJAX handler (optional)
                // const result = await response.json(); 
                // console.log('ShopMetrics Analytics: WP AJAX response:', result);
                logger.log('ShopMetrics Analytics: Visit tracking request sent via WP AJAX.');
            }
        } catch (error) {
            logger.log('ShopMetrics Analytics: Failed to send WP AJAX request.', error);
        }
    }

    // --- Store UTMs from URL into Cookies --- 
    function storeUtmsFromUrl() {
        const utms = getUtmParams();
        const utmKeys = ['source', 'medium', 'campaign', 'term', 'content'];
        let utmsFound = false;

        utmKeys.forEach(key => {
            if (utms[key]) {
                setCookie('shopmetrics_utm_' + key, utms[key], 365); // Store for 1 year
                utmsFound = true;
                logger.log('ShopMetrics Analytics: Storing UTM '+ key + '=' + utms[key]);
            }
        });

        // Optional: Clear cookies if NO UTMs are present in the current URL?
        // This would reset attribution if someone visits directly after a campaign.
        // Decided against this for now to preserve the last known source.
    }

    // --- Execution --- 
    
    // Store UTMs on page load if present in URL
    storeUtmsFromUrl();
    
    // Track every page view to capture the full user journey
    // This is important for marketing analytics and conversion tracking
    let currentPath = document.location.pathname + document.location.search;
    
    // Wait for the DOM to be ready, though generally not strictly necessary for this data
    if (document.readyState === 'loading') { 
        document.addEventListener('DOMContentLoaded', trackPageView);
    } else { 
        trackPageView(); // DOM already ready
    }

})(); 