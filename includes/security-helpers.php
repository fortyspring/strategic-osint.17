<?php
/**
 * Security & Sanitization Helper Functions
 * 
 * This file provides centralized security functions to prevent SQL Injection,
 * XSS, and other common vulnerabilities in Beiruttime OSINT Pro.
 * 
 * Usage: Include this file and use these helper functions instead of direct $_GET/$_POST access.
 * 
 * @package Beiruttime_OSINT_Pro
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

/**
 * Sanitize and validate integer input
 * 
 * @param mixed $value The value to sanitize
 * @param int $default Default value if validation fails
 * @return int Sanitized integer
 */
function bt_sanitize_int($value, $default = 0) {
    if ($value === null || $value === '') {
        return $default;
    }
    return intval($value);
}

/**
 * Sanitize text input for database storage
 * Removes tags, strips slashes, trims whitespace
 * 
 * @param string $value The value to sanitize
 * @param string $default Default value if empty
 * @return string Sanitized text
 */
function bt_sanitize_text($value, $default = '') {
    if ($value === null) {
        return $default;
    }
    $value = wp_unslash($value);
    $value = sanitize_text_field($value);
    return $value !== '' ? $value : $default;
}

/**
 * Sanitize text area input (allows newlines)
 * 
 * @param string $value The value to sanitize
 * @param string $default Default value if empty
 * @return string Sanitized text area content
 */
function bt_sanitize_textarea($value, $default = '') {
    if ($value === null) {
        return $default;
    }
    $value = wp_unslash($value);
    $value = sanitize_textarea_field($value);
    return $value !== '' ? $value : $default;
}

/**
 * Sanitize email address
 * 
 * @param string $value The email to sanitize
 * @param string $default Default value if invalid
 * @return string Sanitized email
 */
function bt_sanitize_email($value, $default = '') {
    if ($value === null) {
        return $default;
    }
    $value = sanitize_email(wp_unslash($value));
    return $value !== '' ? $value : $default;
}

/**
 * Sanitize URL
 * 
 * @param string $value The URL to sanitize
 * @param string $default Default value if invalid
 * @return string Sanitized URL
 */
function bt_sanitize_url($value, $default = '') {
    if ($value === null) {
        return $default;
    }
    $value = esc_url_raw(wp_unslash($value));
    return $value !== '' ? $value : $default;
}

/**
 * Get sanitized GET parameter
 * 
 * @param string $key Parameter name
 * @param string $type Type of sanitization ('int', 'text', 'email', 'url', 'textarea')
 * @param mixed $default Default value if not set
 * @return mixed Sanitized value
 */
function bt_get_param($key, $type = 'text', $default = '') {
    if (!isset($_GET[$key])) {
        return $default;
    }
    
    $value = $_GET[$key];
    
    switch ($type) {
        case 'int':
            return bt_sanitize_int($value, is_numeric($default) ? $default : 0);
        case 'text':
            return bt_sanitize_text($value, $default);
        case 'email':
            return bt_sanitize_email($value, $default);
        case 'url':
            return bt_sanitize_url($value, $default);
        case 'textarea':
            return bt_sanitize_textarea($value, $default);
        default:
            return bt_sanitize_text($value, $default);
    }
}

/**
 * Get sanitized POST parameter
 * 
 * @param string $key Parameter name
 * @param string $type Type of sanitization ('int', 'text', 'email', 'url', 'textarea')
 * @param mixed $default Default value if not set
 * @return mixed Sanitized value
 */
function bt_post_param($key, $type = 'text', $default = '') {
    if (!isset($_POST[$key])) {
        return $default;
    }
    
    $value = $_POST[$key];
    
    switch ($type) {
        case 'int':
            return bt_sanitize_int($value, is_numeric($default) ? $default : 0);
        case 'text':
            return bt_sanitize_text($value, $default);
        case 'email':
            return bt_sanitize_email($value, $default);
        case 'url':
            return bt_sanitize_url($value, $default);
        case 'textarea':
            return bt_sanitize_textarea($value, $default);
        default:
            return bt_sanitize_text($value, $default);
    }
}

/**
 * Escape output for HTML context
 * 
 * @param string $value Value to escape
 * @return string Escaped value
 */
function bt_esc_html($value) {
    return esc_html($value);
}

/**
 * Escape output for HTML attribute context
 * 
 * @param string $value Value to escape
 * @return string Escaped value
 */
function bt_esc_attr($value) {
    return esc_attr($value);
}

/**
 * Escape output for JavaScript context
 * 
 * @param string $value Value to escape
 * @return string Escaped value
 */
function bt_esc_js($value) {
    return esc_js($value);
}

/**
 * Escape URL for output
 * 
 * @param string $value URL to escape
 * @return string Escaped URL
 */
function bt_esc_url($value) {
    return esc_url($value);
}

/**
 * Verify nonce for AJAX requests
 * 
 * @param string $nonce Nonce value
 * @param string $action Nonce action
 * @return bool True if valid, false otherwise
 */
function bt_verify_ajax_nonce($nonce, $action = SOD_AJAX_NONCE_ACTION) {
    if (empty($nonce)) {
        return false;
    }
    return wp_verify_nonce($nonce, $action) !== false;
}

/**
 * Check AJAX nonce and die if invalid
 * 
 * @param string $action Nonce action
 */
function bt_check_ajax_nonce($action = SOD_AJAX_NONCE_ACTION) {
    $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field($_REQUEST['_wpnonce']) : '';
    
    if (empty($nonce) || !bt_verify_ajax_nonce($nonce, $action)) {
        wp_send_json_error([
            'message' => __('Security check failed. Please refresh and try again.', 'beiruttime-osint-pro'),
            'code' => 'nonce_verification_failed'
        ], 403);
    }
}

/**
 * Safe database query wrapper with automatic preparation
 * 
 * @param wpdb $wpdb WordPress database object
 * @param string $query SQL query with placeholders (%s, %d, %f)
 * @param array $params Parameters to substitute
 * @return mixed Query result
 */
function bt_db_query($wpdb, $query, $params = []) {
    if (empty($params)) {
        return $wpdb->query($query);
    }
    $prepared = $wpdb->prepare($query, $params);
    return $wpdb->query($prepared);
}

/**
 * Safe get_results wrapper with automatic preparation
 * 
 * @param wpdb $wpdb WordPress database object
 * @param string $query SQL query with placeholders
 * @param array $params Parameters to substitute
 * @param string $output Format constant (ARRAY_A, OBJECT, etc.)
 * @return array|object|null Query results
 */
function bt_db_get_results($wpdb, $query, $params = [], $output = OBJECT) {
    if (empty($params)) {
        return $wpdb->get_results($query, $output);
    }
    $prepared = $wpdb->prepare($query, $params);
    return $wpdb->get_results($prepared, $output);
}

/**
 * Safe get_row wrapper with automatic preparation
 * 
 * @param wpdb $wpdb WordPress database object
 * @param string $query SQL query with placeholders
 * @param array $params Parameters to substitute
 * @param string $output Format constant
 * @return array|object|null Single row
 */
function bt_db_get_row($wpdb, $query, $params = [], $output = OBJECT) {
    if (empty($params)) {
        return $wpdb->get_row($query, $output);
    }
    $prepared = $wpdb->prepare($query, $params);
    return $wpdb->get_row($prepared, $output);
}

/**
 * Safe get_var wrapper with automatic preparation
 * 
 * @param wpdb $wpdb WordPress database object
 * @param string $query SQL query with placeholders
 * @param array $params Parameters to substitute
 * @return mixed Single value
 */
function bt_db_get_var($wpdb, $query, $params = []) {
    if (empty($params)) {
        return $wpdb->get_var($query);
    }
    $prepared = $wpdb->prepare($query, $params);
    return $wpdb->get_var($prepared);
}

/**
 * Build safe IN clause for queries
 * 
 * @param array $values Array of values for IN clause
 * @return string Comma-separated list of placeholders
 */
function bt_build_in_clause($values) {
    if (empty($values) || !is_array($values)) {
        return '0'; // Safe fallback
    }
    
    // Ensure all values are integers for ID lists
    $values = array_map('intval', $values);
    
    if (empty($values)) {
        return '0';
    }
    
    return implode(',', array_fill(0, count($values), '%d'));
}

/**
 * Validate and sanitize file upload
 * 
 * @param array $file File from $_FILES
 * @param array $allowed_mimes Allowed MIME types
 * @return array Validation result ['success'=>bool, 'error'=>string, 'file'=>array]
 */
function bt_validate_file_upload($file, $allowed_mimes = ['image/jpeg', 'image/png', 'application/pdf']) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return [
            'success' => false,
            'error' => __('File upload error.', 'beiruttime-osint-pro')
        ];
    }
    
    // Check file size (max 5MB by default)
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        return [
            'success' => false,
            'error' => __('File too large. Maximum size is 5MB.', 'beiruttime-osint-pro')
        ];
    }
    
    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_mimes, true)) {
        return [
            'success' => false,
            'error' => __('Invalid file type.', 'beiruttime-osint-pro')
        ];
    }
    
    return [
        'success' => true,
        'error' => '',
        'file' => $file
    ];
}

/**
 * Log security event for auditing
 * 
 * @param string $event_type Type of security event
 * @param string $message Event description
 * @param array $context Additional context data
 */
function bt_log_security_event($event_type, $message, $context = []) {
    if (!function_exists('so_log_message')) {
        return;
    }
    
    $log_data = array_merge([
        'event_type' => $event_type,
        'user_id' => get_current_user_id(),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'timestamp' => current_time('mysql')
    ], $context);
    
    so_log_message("SECURITY_{$event_type}", $message, $log_data);
}
