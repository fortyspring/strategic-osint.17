<?php
/**
 * Plugin Name: Disable OSINT Frontend Notifications
 * Description: Stops OSINT dashboard notifications from appearing on the frontend homepage.
 * Version: 1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Remove OSINT notifications and splash screen from frontend
add_action('wp_footer', 'disable_osint_frontend_notifications', 999);
function disable_osint_frontend_notifications() {
    if (!is_admin()) {
        ?>
        <style>
            /* Hide OSINT notification elements */
            .so-splash, 
            .osint-notification, 
            .osint-notice, 
            [class*="osint"], 
            [id*="osint"] {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                pointer-events: none !important;
            }
        </style>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            // Remove any OSINT notification elements
            var selectors = [
                '.so-splash', 
                '.osint-notification', 
                '.osint-notice', 
                '[class*="osint"]', 
                '[id*="osint"]'
            ];
            
            selectors.forEach(function(selector) {
                var elements = document.querySelectorAll(selector);
                elements.forEach(function(el){
                    if (el && el.parentNode) {
                        el.style.display = 'none';
                        el.style.visibility = 'hidden';
                        // Optionally remove completely after a delay
                        setTimeout(function(){
                            if (el && el.parentNode) {
                                el.remove();
                            }
                        }, 100);
                    }
                });
            });
            
            // Stop any OSINT related AJAX requests
            if (window.XMLHttpRequest) {
                var originalOpen = XMLHttpRequest.prototype.open;
                XMLHttpRequest.prototype.open = function(method, url) {
                    if (url && url.toString().indexOf('osint') !== -1) {
                        return; // Block OSINT AJAX requests on frontend
                    }
                    return originalOpen.apply(this, arguments);
                };
            }
        });
        </script>
        <?php
    }
}

// Disable OSINT scripts/styles on frontend
add_action('wp_enqueue_scripts', 'disable_osint_frontend_assets', 999);
function disable_osint_frontend_assets() {
    if (!is_admin()) {
        // Dequeue common OSINT script handles (adjust based on your actual script names)
        wp_dequeue_script('osint-admin-bar-notice');
        wp_dequeue_script('osint-notifications');
        wp_dequeue_script('osint-splash');
        wp_dequeue_script('admin-bar-notice'); // The specific script causing JSON error
        
        wp_dequeue_style('osint-notifications');
        wp_dequeue_style('osint-splash');
        
        // Deregister to prevent re-loading
        wp_deregister_script('admin-bar-notice');
        wp_deregister_script('osint-admin-bar-notice');
    }
}

// Additional fix: Block the specific AJAX call causing 403 and JSON errors
add_action('wp_footer', 'block_osint_ajax_errors', 1000);
function block_osint_ajax_errors() {
    if (!is_admin()) {
        ?>
        <script>
        (function() {
            // Override fetch to block OSINT analytics/tracking calls
            var originalFetch = window.fetch;
            window.fetch = function(url, options) {
                if (typeof url === 'string' && url.indexOf('admin-ajax.php') !== -1) {
                    // Block specific OSINT actions that cause errors
                    if (options && options.body) {
                        try {
                            var bodyStr = typeof options.body === 'string' ? options.body : JSON.stringify(options.body);
                            if (bodyStr.indexOf('action=so_') !== -1 || bodyStr.indexOf('osint') !== -1) {
                                console.log('Blocked OSINT AJAX request:', url);
                                return Promise.resolve({ ok: false, status: 403 });
                            }
                        } catch(e) {}
                    }
                }
                return originalFetch.apply(this, arguments);
            };
            
            // Also intercept XMLHttpRequest for older code
            var originalXHR = XMLHttpRequest.prototype.open;
            XMLHttpRequest.prototype.open = function(method, url) {
                if (typeof url === 'string' && url.indexOf('admin-ajax.php') !== -1) {
                    this.addEventListener('send', function() {
                        // Check if this is an OSINT request after send is called
                    });
                }
                return originalXHR.apply(this, arguments);
            };
        })();
        </script>
        <?php
    }
}
