/**
 * ShopMetrics Analytics - Direct PostHog Integration
 * No longer proxies through PHP - direct frontend to PostHog communication
 */
(function($) {
    'use strict';
    
    // Initialize PostHog if not already done by React
    if (typeof window.posthog === 'undefined' && shopmetricsAnalytics.enabled) {
        logger.log('[ShopMetrics PHP Analytics] Initializing PostHog for PHP pages');
        
        // Load PostHog script dynamically
        var script = document.createElement('script');
        script.src = shopmetricsAnalytics.posthogHost + '/static/array.js';
        script.async = true;
        script.onload = function() {
            // Initialize PostHog after script loads
            window.posthog.init(shopmetricsAnalytics.posthogKey, {
                api_host: shopmetricsAnalytics.posthogHost,
                person_profiles: 'never', // Privacy-first: no user profiling
                capture_pageview: false, // We'll manually track page views
                capture_pageleave: false,
                disable_session_recording: false,
                session_recording: shopmetricsAnalytics.sessionRecording || {},
                persistence: 'localStorage+cookie',
                persistence_name: 'shopmetrics_ph',
                cookie_name: 'shopmetrics_ph',
                cross_subdomain_cookie: false,
                secure_cookie: true,
                debug: false
            });
            
            // Set super properties for all events
            window.posthog.register({
                site_hash: shopmetricsAnalytics.siteHash,
                plugin_version: shopmetricsAnalytics.pluginVersion,
                source: 'php_admin'
            });
            
            logger.log('[ShopMetrics PHP Analytics] PostHog initialized for PHP admin pages');
        };
        document.head.appendChild(script);
    }
    
    window.ShopMetricsAnalytics = {
        /**
         * Track an event directly to PostHog (no PHP proxy)
         */
        track: function(eventName, properties, callback) {
            if (!shopmetricsAnalytics.enabled) {
                logger.log('[ShopMetrics Analytics] Analytics disabled');
                if (callback) callback(false);
                return Promise.resolve(false);
            }
            
            logger.log('[ShopMetrics Analytics] Tracking event directly to PostHog:', eventName, properties);
            
            if (typeof window.posthog !== 'undefined') {
                try {
                    window.posthog.capture(eventName, properties || {});
                    logger.log('[ShopMetrics Analytics] Event sent to PostHog successfully');
                    if (callback) callback(true);
                    return Promise.resolve(true);
                } catch (error) {
                    logger.error('[ShopMetrics Analytics] PostHog error:', error);
                    if (callback) callback(false);
                    return Promise.reject(error);
                }
            } else {
                logger.warn('[ShopMetrics Analytics] PostHog not loaded');
                if (callback) callback(false);
                return Promise.resolve(false);
            }
        },
        
        /**
         * Track page view
         */
        trackPageView: function() {
            return this.track('page_viewed', {
                page_url: window.location.href,
                page_title: document.title,
                referrer: document.referrer,
                timestamp: new Date().toISOString(),
                source: 'php_admin'
            });
        },
        
        /**
         * Track button click
         */
        trackButtonClick: function(buttonName, properties) {
            return this.track('button_clicked', {
                button: buttonName,
                source: 'php_admin',
                ...properties
            });
        },

        /**
         * Track feature usage
         */
        trackFeature: function(feature, action, data = {}) {
            return this.track('feature_used', {
                feature: feature,
                feature_action: action,
                ...data
            });
        },

        /**
         * Track tab change
         */
        trackTabChange: function(fromTab, toTab) {
            return this.track('dashboard_tab_changed', {
                from_tab: fromTab,
                to_tab: toTab
            });
        },

        /**
         * Track onboarding event
         */
        trackOnboarding: function(step, action, data = {}) {
            return this.track('onboarding_event', {
                onboarding_step: step,
                onboarding_action: action,
                ...data
            });
        }
    };
    
    // Auto-track page views on admin pages
    $(document).ready(function() {
        if (shopmetricsAnalytics.enabled) {
            logger.log('[ShopMetrics PHP Analytics] Ready - direct PostHog mode');
            
            // Only track page view if we're on a PHP admin page (not React dashboard)
            if (!document.getElementById('shopmetrics-dashboard-root')) {
                ShopMetricsAnalytics.trackPageView();
            }
            
            // Auto-track clicks on elements with data-analytics-event
            $(document).on('click', '[data-analytics-event]', function() {
                const $el = $(this);
                const event = $el.data('analytics-event');
                const properties = $el.data('analytics-properties') || {};
                
                ShopMetricsAnalytics.track(event, properties);
            });
        } else {
            logger.log('[ShopMetrics PHP Analytics] Analytics disabled');
        }
    });
    
    window.logger = window.logger || { log: (...args) => console.log(...args) };
    
})(jQuery); 