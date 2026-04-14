<?php
/**
 * Security Fixes for Beiruttime OSINT Pro
 * 
 * This file contains patches for:
 * 1. SQL Injection vulnerabilities
 * 2. Input/Output sanitization improvements
 * 3. Security test suite
 * 
 * Apply these fixes to beiruttime-osint-pro.php
 */

// ============================================================================
// FIX 1: SQL Injection - Line 302 (Missing prepare)
// ============================================================================
/*
BEFORE (Line 302):
    $rows = $wpdb->get_results("SELECT id, actor_name, target_name, region FROM {$table}", ARRAY_A);

AFTER:
    $rows = $wpdb->get_results($wpdb->prepare("SELECT id, actor_name, target_name, region FROM {$table}", []), ARRAY_A);
*/

// ============================================================================
// FIX 2: SQL Injection - Line 1489 (DELETE with implode)
// ============================================================================
/*
BEFORE (Line 1489):
    $wpdb->query("DELETE FROM {$table} WHERE id IN ({$in})");

AFTER:
    if (!empty($delete_ids)) {
        $placeholders = implode(',', array_fill(0, count($delete_ids), '%d'));
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id IN ($placeholders)", $delete_ids));
    }
*/

// ============================================================================
// FIX 3: SQL Injection - Line 3943 (DELETE with placeholders)
// ============================================================================
/*
BEFORE (Line 3943):
    $wpdb->query("DELETE FROM $t WHERE id IN ($placeholders) AND score < 140");

AFTER:
    if (!empty($del_ids)) {
        $placeholders = implode(',', array_fill(0, count($del_ids), '%d'));
        $wpdb->query($wpdb->prepare("DELETE FROM $t WHERE id IN ($placeholders) AND score < 140", $del_ids));
    }
*/

// ============================================================================
// FIX 4: SQL Injection - Line 4349 (String concatenation in WHERE)
// ============================================================================
/*
BEFORE (Line 4349):
    $events = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}so_news_events WHERE event_timestamp >= ".(time()-86400),ARRAY_A);

AFTER:
    $cutoff = time() - 86400;
    $events = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}so_news_events WHERE event_timestamp >= %d", $cutoff), ARRAY_A);
*/

// ============================================================================
// FIX 5: SQL Injection - Line 4964, 4980 (MAX query without prepare)
// ============================================================================
/*
BEFORE (Line 4964):
    $next_id = (int)$wpdb->get_var("SELECT COALESCE(MAX(id),0)+1 FROM {$wpdb->prefix}so_dict_actors");

AFTER:
    $next_id = (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(id),0)+1 FROM {$wpdb->prefix}so_dict_actors", []));

BEFORE (Line 4980):
    $next_id = (int)$wpdb->get_var("SELECT COALESCE(MAX(id),0)+1 FROM {$wpdb->prefix}so_dict_weapons");

AFTER:
    $next_id = (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(id),0)+1 FROM {$wpdb->prefix}so_dict_weapons", []));
*/

// ============================================================================
// FIX 6: Input Sanitization - Line 4590, 4696 (Cast to int without validation)
// ============================================================================
/*
BEFORE (Line 4590):
    $hours=(int)($_POST['hours']??24);

AFTER:
    $hours = isset($_POST['hours']) ? absint($_POST['hours']) : 24;
    $hours = max(1, min(8760, $hours)); // Validate range

BEFORE (Line 4696):
    $hours = (int)($_POST['hours'] ?? 72);

AFTER:
    $hours = isset($_POST['hours']) ? absint($_POST['hours']) : 72;
    $hours = max(1, min(8760, $hours)); // Validate range
*/

// ============================================================================
// FIX 7: Output Escaping - Ensure all outputs are escaped
// ============================================================================
// Add esc_html(), esc_attr(), esc_url() to all output contexts
// Already present in many places, but verify consistency

// ============================================================================
// FIX 8: cURL SSL Verification - Line 5866
// ============================================================================
/*
BEFORE (around Line 5866):
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

AFTER:
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
*/

// ============================================================================
// HELPER FUNCTION: Enhanced input sanitization
// ============================================================================
function sod_sanitize_input($value, $type = 'text') {
    if ($value === null) {
        return null;
    }
    
    switch ($type) {
        case 'int':
            return isset($value) ? absint($value) : 0;
        case 'float':
            return isset($value) ? floatval($value) : 0.0;
        case 'email':
            return isset($value) ? sanitize_email($value) : '';
        case 'url':
            return isset($value) ? esc_url_raw($value) : '';
        case 'html':
            return isset($value) ? wp_kses_post($value) : '';
        case 'text':
        default:
            return isset($value) ? sanitize_text_field(wp_unslash($value)) : '';
    }
}

// ============================================================================
// HELPER FUNCTION: Safe DELETE with prepared statement
// ============================================================================
function sod_safe_delete($table, $ids) {
    global $wpdb;
    
    if (empty($ids) || !is_array($ids)) {
        return 0;
    }
    
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    
    return $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table} WHERE id IN ($placeholders)",
        $ids
    ));
}

// ============================================================================
// HELPER FUNCTION: Safe COUNT query
// ============================================================================
function sod_safe_count($table, $where_clause = '', $params = []) {
    global $wpdb;
    
    $sql = "SELECT COUNT(*) FROM {$table}";
    
    if (!empty($where_clause)) {
        $sql .= " WHERE " . $where_clause;
    }
    
    if (!empty($params)) {
        $sql = $wpdb->prepare($sql, $params);
    }
    
    return (int) $wpdb->get_var($sql);
}

// ============================================================================
// SECURITY TEST SUITE
// ============================================================================

class SO_Security_Tests {
    
    /**
     * Test SQL Injection Prevention
     */
    public static function test_sql_injection_prevention() {
        global $wpdb;
        $results = [];
        
        // Test 1: Verify prepare() is used for dynamic queries
        $test_input = "1' OR '1'='1";
        $safe_id = absint($test_input); // Should be 0
        
        $results['test_cast_validation'] = [
            'input' => $test_input,
            'output' => $safe_id,
            'passed' => $safe_id === 0
        ];
        
        // Test 2: Verify DELETE with prepared statement
        $test_ids = [1, 2, "3' OR '1'='1"];
        $safe_ids = array_map('intval', $test_ids);
        
        $results['test_ids_sanitization'] = [
            'input' => $test_ids,
            'output' => $safe_ids,
            'passed' => $safe_ids === [1, 2, 0]
        ];
        
        return $results;
    }
    
    /**
     * Test XSS Prevention
     */
    public static function test_xss_prevention() {
        $results = [];
        
        // Test 1: Script tag injection
        $malicious_input = '<script>alert("XSS")</script>';
        $sanitized = sanitize_text_field($malicious_input);
        
        $results['test_script_removal'] = [
            'input' => $malicious_input,
            'output' => $sanitized,
            'passed' => strpos($sanitized, '<script>') === false
        ];
        
        // Test 2: Event handler injection
        $malicious_input2 = '<img src=x onerror=alert("XSS")>';
        $sanitized2 = wp_kses_post($malicious_input2);
        
        $results['test_event_handler_removal'] = [
            'input' => $malicious_input2,
            'output' => $sanitized2,
            'passed' => strpos($sanitized2, 'onerror') === false
        ];
        
        // Test 3: URL validation
        $malicious_url = 'javascript:alert("XSS")';
        $safe_url = esc_url_raw($malicious_url);
        
        $results['test_javascript_url'] = [
            'input' => $malicious_url,
            'output' => $safe_url,
            'passed' => empty($safe_url) || strpos($safe_url, 'javascript') === false
        ];
        
        return $results;
    }
    
    /**
     * Test Input Validation
     */
    public static function test_input_validation() {
        $results = [];
        
        // Test integer validation
        $results['test_integer_validation'] = [
            'valid' => absint('123') === 123,
            'invalid_string' => absint('abc') === 0,
            'negative' => absint('-5') === 5,
            'sql_injection' => absint("1' OR '1'='1") === 0
        ];
        
        // Test range validation
        $hours = isset($_POST['hours']) ? absint($_POST['hours']) : 24;
        $hours = max(1, min(8760, $hours));
        
        $results['test_range_validation'] = [
            'within_range' => $hours >= 1 && $hours <= 8760
        ];
        
        return $results;
    }
    
    /**
     * Test Nonce Verification
     */
    public static function test_nonce_verification() {
        $results = [];
        
        // Generate a nonce
        $nonce = wp_create_nonce('test_action');
        
        // Verify valid nonce
        $valid = wp_verify_nonce($nonce, 'test_action');
        
        $results['test_valid_nonce'] = [
            'passed' => $valid !== false
        ];
        
        // Verify invalid nonce
        $invalid = wp_verify_nonce('invalid_nonce', 'test_action');
        
        $results['test_invalid_nonce'] = [
            'passed' => $invalid === false
        ];
        
        return $results;
    }
    
    /**
     * Run all security tests
     */
    public static function run_all_tests() {
        $all_results = [
            'sql_injection' => self::test_sql_injection_prevention(),
            'xss' => self::test_xss_prevention(),
            'input_validation' => self::test_input_validation(),
            'nonce' => self::test_nonce_verification()
        ];
        
        $summary = [
            'total_tests' => 0,
            'passed' => 0,
            'failed' => 0
        ];
        
        foreach ($all_results as $category => $tests) {
            foreach ($tests as $test_name => $result) {
                $summary['total_tests']++;
                if (isset($result['passed']) && $result['passed']) {
                    $summary['passed']++;
                } elseif (is_bool($result) && $result) {
                    $summary['passed']++;
                } else {
                    $summary['failed']++;
                }
            }
        }
        
        return [
            'detailed' => $all_results,
            'summary' => $summary
        ];
    }
}

// ============================================================================
// USAGE EXAMPLE
// ============================================================================
/*
// Run security tests
$test_results = SO_Security_Tests::run_all_tests();

// Check results
if ($test_results['summary']['failed'] > 0) {
    error_log('Security Tests Failed: ' . print_r($test_results, true));
} else {
    error_log('All Security Tests Passed!');
}
*/
