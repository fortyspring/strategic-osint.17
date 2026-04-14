<?php
/**
 * Security Helper Functions for Beiruttime OSINT Pro
 * 
 * This file provides centralized security functions to:
 * - Prevent SQL Injection using prepare()
 * - Sanitize all inputs
 * - Escape all outputs
 * - Verify nonces
 * - Validate capabilities
 * 
 * Usage: Include this file in your plugin and use these functions instead of direct $_GET/$_POST access
 */

if (!defined('ABSPATH')) {
    die('Direct access not allowed');
}

/**
 * Safe SQL Query Wrapper - Prevents SQL Injection
 * 
 * @param string $query SQL query with placeholders (%s, %d, %f)
 * @param array $args Arguments to replace placeholders
 * @return string Prepared SQL query
 */
function so_safe_query($query, $args = []) {
    global $wpdb;
    
    if (empty($args)) {
        return $query;
    }
    
    return $wpdb->prepare($query, $args);
}

/**
 * Safe GET Parameter Retrieval
 * 
 * @param string $key Parameter name
 * @param mixed $default Default value if not set
 * @param string $type Expected type ('int', 'float', 'string', 'email', 'url', 'array')
 * @return mixed Sanitized value
 */
function so_get_param($key, $default = null, $type = 'string') {
    if (!isset($_GET[$key])) {
        return $default;
    }
    
    $value = $_GET[$key];
    
    switch ($type) {
        case 'int':
            return intval($value);
        
        case 'float':
            return floatval($value);
        
        case 'string':
            return sanitize_text_field($value);
        
        case 'email':
            return sanitize_email($value);
        
        case 'url':
            return esc_url_raw($value);
        
        case 'array':
            return array_map('sanitize_text_field', (array)$value);
        
        default:
            return sanitize_text_field($value);
    }
}

/**
 * Safe POST Parameter Retrieval
 * 
 * @param string $key Parameter name
 * @param mixed $default Default value if not set
 * @param string $type Expected type ('int', 'float', 'string', 'email', 'url', 'array')
 * @return mixed Sanitized value
 */
function so_post_param($key, $default = null, $type = 'string') {
    if (!isset($_POST[$key])) {
        return $default;
    }
    
    $value = $_POST[$key];
    
    switch ($type) {
        case 'int':
            return intval($value);
        
        case 'float':
            return floatval($value);
        
        case 'string':
            return sanitize_text_field($value);
        
        case 'email':
            return sanitize_email($value);
        
        case 'url':
            return esc_url_raw($value);
        
        case 'array':
            return array_map('sanitize_text_field', (array)$value);
        
        default:
            return sanitize_text_field($value);
    }
}

/**
 * Safe REQUEST Parameter Retrieval
 * 
 * @param string $key Parameter name
 * @param mixed $default Default value if not set
 * @param string $type Expected type ('int', 'float', 'string', 'email', 'url', 'array')
 * @return mixed Sanitized value
 */
function so_request_param($key, $default = null, $type = 'string') {
    if (!isset($_REQUEST[$key])) {
        return $default;
    }
    
    $value = $_REQUEST[$key];
    
    switch ($type) {
        case 'int':
            return intval($value);
        
        case 'float':
            return floatval($value);
        
        case 'string':
            return sanitize_text_field($value);
        
        case 'email':
            return sanitize_email($value);
        
        case 'url':
            return esc_url_raw($value);
        
        case 'array':
            return array_map('sanitize_text_field', (array)$value);
        
        default:
            return sanitize_text_field($value);
    }
}

/**
 * Verify Nonce and Die if Invalid
 * 
 * @param string $action Action name
 * @param string $field Nonce field name (default: '_wpnonce')
 */
function so_verify_nonce_or_die($action, $field = '_wpnonce') {
    if (!isset($_REQUEST[$field]) || !wp_verify_nonce($_REQUEST[$field], $action)) {
        wp_die(
            __('Security check failed. Please refresh and try again.', 'beiruttime-osint'),
            __('Security Error', 'beiruttime-osint'),
            ['response' => 403]
        );
    }
}

/**
 * Check User Capability and Die if Insufficient
 * 
 * @param string $capability Required capability
 */
function so_check_capability_or_die($capability) {
    if (!current_user_can($capability)) {
        wp_die(
            __('You do not have permission to perform this action.', 'beiruttime-osint'),
            __('Permission Denied', 'beiruttime-osint'),
            ['response' => 403]
        );
    }
}

/**
 * Escape Output for HTML Context
 * 
 * @param string $text Text to escape
 * @return string Escaped text
 */
function so_esc_html($text) {
    return esc_html($text);
}

/**
 * Escape Output for Attribute Context
 * 
 * @param string $text Text to escape
 * @return string Escaped text
 */
function so_esc_attr($text) {
    return esc_attr($text);
}

/**
 * Escape Output for URL Context
 * 
 * @param string $url URL to escape
 * @return string Escaped URL
 */
function so_esc_url($url) {
    return esc_url($url);
}

/**
 * Safe Database Insert
 * 
 * @param string $table Table name
 * @param array $data Data to insert
 * @param array|null $format Optional. Data formats for each value
 * @return int|false Number of rows inserted, or false on error
 */
function so_db_insert($table, $data, $format = null) {
    global $wpdb;
    
    // Sanitize data keys
    $safe_data = [];
    foreach ($data as $key => $value) {
        $safe_key = sanitize_key($key);
        $safe_data[$safe_key] = $value;
    }
    
    return $wpdb->insert($table, $safe_data, $format);
}

/**
 * Safe Database Update
 * 
 * @param string $table Table name
 * @param array $data Data to update
 * @param array $where WHERE clause conditions
 * @param array|null $data_format Optional. Data formats for each value
 * @param array|null $where_format Optional. Data formats for WHERE values
 * @return int|false Number of rows updated, or false on error
 */
function so_db_update($table, $data, $where, $data_format = null, $where_format = null) {
    global $wpdb;
    
    // Sanitize data keys
    $safe_data = [];
    foreach ($data as $key => $value) {
        $safe_key = sanitize_key($key);
        $safe_data[$safe_key] = $value;
    }
    
    // Sanitize where keys
    $safe_where = [];
    foreach ($where as $key => $value) {
        $safe_key = sanitize_key($key);
        $safe_where[$safe_key] = $value;
    }
    
    return $wpdb->update($table, $safe_data, $safe_where, $data_format, $where_format);
}

/**
 * Safe Database Delete
 * 
 * @param string $table Table name
 * @param array $where WHERE clause conditions
 * @param array|null $format Optional. Data formats for WHERE values
 * @return int|false Number of rows deleted, or false on error
 */
function so_db_delete($table, $where, $format = null) {
    global $wpdb;
    
    // Sanitize where keys
    $safe_where = [];
    foreach ($where as $key => $value) {
        $safe_key = sanitize_key($key);
        $safe_where[$safe_key] = $value;
    }
    
    return $wpdb->delete($table, $safe_where, $format);
}

/**
 * Safe Database Get Results
 * 
 * @param string $query SQL query (should use prepare())
 * @param string $output Constant for return type (OBJECT, ARRAY_A, ARRAY_N)
 * @return array|null Results or null on error
 */
function so_db_get_results($query, $output = OBJECT) {
    global $wpdb;
    
    return $wpdb->get_results($query, $output);
}

/**
 * Safe Database Get Row
 * 
 * @param string $query SQL query (should use prepare())
 * @param string $output Constant for return type (OBJECT, ARRAY_A, ARRAY_N)
 * @return array|object|null Row or null on error
 */
function so_db_get_row($query, $output = OBJECT) {
    global $wpdb;
    
    return $wpdb->get_row($query, $output);
}

/**
 * Safe Database Get Var
 * 
 * @param string $query SQL query (should use prepare())
 * @param int $x Column index
 * @param int $y Row index
 * @return mixed Variable value or null on error
 */
function so_db_get_var($query = null, $x = 0, $y = 0) {
    global $wpdb;
    
    return $wpdb->get_var($query, $x, $y);
}

/**
 * Validate and Sanitize File Upload
 * 
 * @param array $file File from $_FILES
 * @param array $allowed_types Allowed MIME types
 * @param int $max_size Maximum file size in bytes
 * @return array Validation result ['success'=>bool, 'error'=>string, 'file'=>array]
 */
function so_validate_file_upload($file, $allowed_types = [], $max_size = null) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return [
            'success' => false,
            'error' => __('File upload failed.', 'beiruttime-osint'),
            'file' => null
        ];
    }
    
    // Check file size
    if ($max_size && $file['size'] > $max_size) {
        return [
            'success' => false,
            'error' => sprintf(__('File size exceeds maximum allowed size of %s.', 'beiruttime-osint'), size_format($max_size)),
            'file' => null
        ];
    }
    
    // Check file type
    $file_info = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $file_info->file($file['tmp_name']);
    
    if (!empty($allowed_types) && !in_array($mime_type, $allowed_types, true)) {
        return [
            'success' => false,
            'error' => __('File type is not allowed.', 'beiruttime-osint'),
            'file' => null
        ];
    }
    
    // Generate safe filename
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safe_filename = wp_unique_filename(wp_upload_dir()['path'], 'file_' . time() . '.' . $file_ext);
    
    return [
        'success' => true,
        'error' => null,
        'file' => [
            'tmp_name' => $file['tmp_name'],
            'name' => $safe_filename,
            'type' => $mime_type,
            'size' => $file['size']
        ]
    ];
}

/**
 * Log Security Event
 * 
 * @param string $event Event type
 * @param string $message Event message
 * @param array $context Additional context data
 */
function so_log_security_event($event, $message, $context = []) {
    if (!function_exists('so_write_log')) {
        // Fallback if main plugin functions not loaded
        error_log("[Beiruttime OSINT Security] {$event}: {$message}");
        return;
    }
    
    so_write_log("SECURITY_EVENT", [
        'event' => $event,
        'message' => $message,
        'context' => $context,
        'user_id' => get_current_user_id(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'timestamp' => current_time('mysql')
    ]);
}
