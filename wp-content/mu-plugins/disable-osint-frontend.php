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
        
        wp_dequeue_style('osint-notifications');
        wp_dequeue_style('osint-splash');
    }
}
