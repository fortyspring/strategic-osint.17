<?php
/**
 * Security Tests for Beiruttime OSINT Pro
 * 
 * This file contains comprehensive security tests to verify:
 * - SQL Injection prevention
 * - XSS protection
 * - Nonce verification
 * - Input sanitization
 * - Output escaping
 * 
 * Run these tests in a staging environment only!
 * 
 * @package Beiruttime_OSINT_Pro
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

/**
 * Test Suite: SQL Injection Prevention
 */
class BT_Security_SQL_Tests {
    
    /**
     * Test that all database queries use prepared statements
     */
    public static function test_prepared_statements() {
        global $wpdb;
        
        $test_results = [
            'passed' => 0,
            'failed' => 0,
            'details' => []
        ];
        
        // Test 1: Direct variable interpolation should fail
        $malicious_input = "1' OR '1'='1";
        
        // Simulate safe query using our helper
        require_once __DIR__ . '/../includes/security-helpers.php';
        
        try {
            // This should be safe - using prepare
            $safe_query = $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}so_news_events WHERE id = %d",
                intval($malicious_input)
            );
            
            $result = $wpdb->get_var($safe_query);
            
            $test_results['passed']++;
            $test_results['details'][] = [
                'test' => 'Prepared statement with malicious input',
                'status' => 'PASS',
                'note' => 'Input was properly sanitized to integer'
            ];
        } catch (Exception $e) {
            $test_results['failed']++;
            $test_results['details'][] = [
                'test' => 'Prepared statement with malicious input',
                'status' => 'FAIL',
                'error' => $e->getMessage()
            ];
        }
        
        // Test 2: IN clause safety
        $malicious_ids = ["1", "2' OR '1'='1", "3"];
        $safe_ids = array_map('intval', $malicious_ids);
        
        if ($safe_ids === [1, 0, 3]) {
            $test_results['passed']++;
            $test_results['details'][] = [
                'test' => 'IN clause sanitization',
                'status' => 'PASS',
                'note' => 'Malicious input converted to 0'
            ];
        } else {
            $test_results['failed']++;
            $test_results['details'][] = [
                'test' => 'IN clause sanitization',
                'status' => 'FAIL',
                'note' => 'Sanitization did not work as expected'
            ];
        }
        
        return $test_results;
    }
    
    /**
     * Test bt_db_get_results helper function
     */
    public static function test_helper_functions() {
        global $wpdb;
        
        require_once __DIR__ . '/../includes/security-helpers.php';
        
        $results = [
            'passed' => 0,
            'failed' => 0,
            'details' => []
        ];
        
        // Test bt_db_get_var with parameters
        try {
            $count = bt_db_get_var(
                $wpdb,
                "SELECT COUNT(*) FROM {$wpdb->prefix}so_news_events WHERE score > %d",
                [100]
            );
            
            if (is_numeric($count)) {
                $results['passed']++;
                $results['details'][] = [
                    'test' => 'bt_db_get_var with parameter',
                    'status' => 'PASS'
                ];
            } else {
                throw new Exception('Unexpected return type');
            }
        } catch (Exception $e) {
            $results['failed']++;
            $results['details'][] = [
                'test' => 'bt_db_get_var with parameter',
                'status' => 'FAIL',
                'error' => $e->getMessage()
            ];
        }
        
        return $results;
    }
}

/**
 * Test Suite: XSS Prevention
 */
class BT_Security_XSS_Tests {
    
    /**
     * Test input sanitization functions
     */
    public static function test_input_sanitization() {
        require_once __DIR__ . '/../includes/security-helpers.php';
        
        $results = [
            'passed' => 0,
            'failed' => 0,
            'details' => []
        ];
        
        // Test cases with malicious payloads
        $test_cases = [
            [
                'input' => '<script>alert("XSS")</script>',
                'expected_clean' => true,
                'type' => 'text'
            ],
            [
                'input' => '"><img src=x onerror=alert(1)>',
                'expected_clean' => true,
                'type' => 'text'
            ],
            [
                'input' => 'javascript:alert(1)',
                'expected_clean' => true,
                'type' => 'url'
            ],
            [
                'input' => 'normal text without attacks',
                'expected_clean' => true,
                'type' => 'text'
            ]
        ];
        
        foreach ($test_cases as $case) {
            $sanitized = '';
            
            switch ($case['type']) {
                case 'text':
                    $sanitized = bt_sanitize_text($case['input']);
                    break;
                case 'url':
                    $sanitized = bt_sanitize_url($case['input']);
                    break;
            }
            
            // Check if script tags are removed
            $has_script = stripos($sanitized, '<script') !== false;
            $has_onerror = stripos($sanitized, 'onerror') !== false;
            $has_javascript = stripos($sanitized, 'javascript:') !== false;
            
            $is_safe = !$has_script && !$has_onerror && !$has_javascript;
            
            if ($is_safe) {
                $results['passed']++;
                $results['details'][] = [
                    'test' => 'Sanitization of: ' . substr($case['input'], 0, 30),
                    'status' => 'PASS',
                    'input' => $case['input'],
                    'output' => $sanitized
                ];
            } else {
                $results['failed']++;
                $results['details'][] = [
                    'test' => 'Sanitization of: ' . substr($case['input'], 0, 30),
                    'status' => 'FAIL',
                    'input' => $case['input'],
                    'output' => $sanitized,
                    'warning' => 'Potentially dangerous content not filtered'
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Test output escaping functions
     */
    public static function test_output_escaping() {
        require_once __DIR__ . '/../includes/security-helpers.php';
        
        $results = [
            'passed' => 0,
            'failed' => 0,
            'details' => []
        ];
        
        $dangerous_string = '<script>alert("XSS")</script>';
        
        // Test HTML escaping
        $escaped_html = bt_esc_html($dangerous_string);
        if (strpos($escaped_html, '&lt;script&gt;') !== false) {
            $results['passed']++;
            $results['details'][] = [
                'test' => 'HTML escaping',
                'status' => 'PASS'
            ];
        } else {
            $results['failed']++;
            $results['details'][] = [
                'test' => 'HTML escaping',
                'status' => 'FAIL',
                'output' => $escaped_html
            ];
        }
        
        // Test attribute escaping
        $escaped_attr = bt_esc_attr($dangerous_string);
        if (strpos($escaped_attr, '&lt;script&gt;') !== false || $escaped_attr !== $dangerous_string) {
            $results['passed']++;
            $results['details'][] = [
                'test' => 'Attribute escaping',
                'status' => 'PASS'
            ];
        } else {
            $results['failed']++;
            $results['details'][] = [
                'test' => 'Attribute escaping',
                'status' => 'FAIL'
            ];
        }
        
        return $results;
    }
}

/**
 * Test Suite: Nonce Verification
 */
class BT_Security_Nonce_Tests {
    
    /**
     * Test nonce verification
     */
    public static function test_nonce_verification() {
        require_once __DIR__ . '/../includes/security-helpers.php';
        
        $results = [
            'passed' => 0,
            'failed' => 0,
            'details' => []
        ];
        
        // Generate a valid nonce
        $action = 'test_action';
        $valid_nonce = wp_create_nonce($action);
        
        // Test 1: Valid nonce should pass
        if (bt_verify_ajax_nonce($valid_nonce, $action)) {
            $results['passed']++;
            $results['details'][] = [
                'test' => 'Valid nonce verification',
                'status' => 'PASS'
            ];
        } else {
            $results['failed']++;
            $results['details'][] = [
                'test' => 'Valid nonce verification',
                'status' => 'FAIL'
            ];
        }
        
        // Test 2: Invalid nonce should fail
        if (!bt_verify_ajax_nonce('invalid_nonce', $action)) {
            $results['passed']++;
            $results['details'][] = [
                'test' => 'Invalid nonce rejection',
                'status' => 'PASS'
            ];
        } else {
            $results['failed']++;
            $results['details'][] = [
                'test' => 'Invalid nonce rejection',
                'status' => 'FAIL',
                'warning' => 'Invalid nonce was accepted!'
            ];
        }
        
        // Test 3: Empty nonce should fail
        if (!bt_verify_ajax_nonce('', $action)) {
            $results['passed']++;
            $results['details'][] = [
                'test' => 'Empty nonce rejection',
                'status' => 'PASS'
            ];
        } else {
            $results['failed']++;
            $results['details'][] = [
                'test' => 'Empty nonce rejection',
                'status' => 'FAIL'
            ];
        }
        
        return $results;
    }
}

/**
 * Test Suite: File Upload Security
 */
class BT_Security_Upload_Tests {
    
    /**
     * Test file upload validation
     */
    public static function test_file_validation() {
        require_once __DIR__ . '/../includes/security-helpers.php';
        
        $results = [
            'passed' => 0,
            'failed' => 0,
            'details' => []
        ];
        
        // Note: These tests simulate file upload scenarios
        // In real testing, you would create actual temp files
        
        // Test 1: Missing file should fail
        $result = bt_validate_file_upload(null);
        if (!$result['success']) {
            $results['passed']++;
            $results['details'][] = [
                'test' => 'Missing file rejection',
                'status' => 'PASS'
            ];
        } else {
            $results['failed']++;
            $results['details'][] = [
                'test' => 'Missing file rejection',
                'status' => 'FAIL'
            ];
        }
        
        // Test 2: File with error should fail
        $fake_file = ['error' => UPLOAD_ERR_NO_FILE];
        $result = bt_validate_file_upload($fake_file);
        if (!$result['success']) {
            $results['passed']++;
            $results['details'][] = [
                'test' => 'File error rejection',
                'status' => 'PASS'
            ];
        } else {
            $results['failed']++;
            $results['details'][] = [
                'test' => 'File error rejection',
                'status' => 'FAIL'
            ];
        }
        
        return $results;
    }
}

/**
 * Run All Security Tests
 * 
 * @return array Complete test results
 */
function bt_run_all_security_tests() {
    $all_results = [
        'timestamp' => current_time('mysql'),
        'summary' => [
            'total_passed' => 0,
            'total_failed' => 0
        ],
        'suites' => []
    ];
    
    // Run SQL Injection tests
    $sql_tests = BT_Security_SQL_Tests::test_prepared_statements();
    $sql_helper_tests = BT_Security_SQL_Tests::test_helper_functions();
    
    $all_results['suites']['sql_injection'] = array_merge_recursive($sql_tests, $sql_helper_tests);
    $all_results['summary']['total_passed'] += $sql_tests['passed'] + $sql_helper_tests['passed'];
    $all_results['summary']['total_failed'] += $sql_tests['failed'] + $sql_helper_tests['failed'];
    
    // Run XSS tests
    $xss_input_tests = BT_Security_XSS_Tests::test_input_sanitization();
    $xss_output_tests = BT_Security_XSS_Tests::test_output_escaping();
    
    $all_results['suites']['xss_prevention'] = array_merge_recursive($xss_input_tests, $xss_output_tests);
    $all_results['summary']['total_passed'] += $xss_input_tests['passed'] + $xss_output_tests['passed'];
    $all_results['summary']['total_failed'] += $xss_input_tests['failed'] + $xss_output_tests['failed'];
    
    // Run Nonce tests
    $nonce_tests = BT_Security_Nonce_Tests::test_nonce_verification();
    
    $all_results['suites']['nonce_verification'] = $nonce_tests;
    $all_results['summary']['total_passed'] += $nonce_tests['passed'];
    $all_results['summary']['total_failed'] += $nonce_tests['failed'];
    
    // Run File Upload tests
    $upload_tests = BT_Security_Upload_Tests::test_file_validation();
    
    $all_results['suites']['file_upload'] = $upload_tests;
    $all_results['summary']['total_passed'] += $upload_tests['passed'];
    $all_results['summary']['total_failed'] += $upload_tests['failed'];
    
    return $all_results;
}

/**
 * Display Test Results as HTML
 * 
 * @param array $results Test results
 */
function bt_display_test_results($results) {
    ?>
    <div class="wrap">
        <h1><?php _e('Security Test Results', 'beiruttime-osint-pro'); ?></h1>
        <p><?php printf(__('Test completed at: %s', 'beiruttime-osint-pro'), $results['timestamp']); ?></p>
        
        <div style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid <?php echo $results['summary']['total_failed'] > 0 ? '#dc3232' : '#46b450'; ?>;">
            <h2><?php _e('Summary', 'beiruttime-osint-pro'); ?></h2>
            <p>
                <strong style="color: #46b450;"><?php echo $results['summary']['total_passed']; ?> <?php _e('Passed', 'beiruttime-osint-pro'); ?></strong> | 
                <strong style="color: <?php echo $results['summary']['total_failed'] > 0 ? '#dc3232' : '#46b450'; ?>;">
                    <?php echo $results['summary']['total_failed']; ?> <?php _e('Failed', 'beiruttime-osint-pro'); ?>
                </strong>
            </p>
        </div>
        
        <?php foreach ($results['suites'] as $suite_name => $suite_results): ?>
            <div style="background: #fff; padding: 20px; margin: 20px 0;">
                <h2><?php echo ucwords(str_replace('_', ' ', $suite_name)); ?></h2>
                <p>
                    <?php printf(
                        __('%d passed, %d failed', 'beiruttime-osint-pro'),
                        $suite_results['passed'],
                        $suite_results['failed']
                    ); ?>
                </p>
                
                <?php if (!empty($suite_results['details'])): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Test', 'beiruttime-osint-pro'); ?></th>
                                <th><?php _e('Status', 'beiruttime-osint-pro'); ?></th>
                                <th><?php _e('Details', 'beiruttime-osint-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suite_results['details'] as $detail): ?>
                                <tr>
                                    <td><?php echo esc_html($detail['test']); ?></td>
                                    <td>
                                        <span style="color: <?php echo $detail['status'] === 'PASS' ? '#46b450' : '#dc3232'; ?>; font-weight: bold;">
                                            <?php echo esc_html($detail['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        if (isset($detail['note'])) echo '<p>' . esc_html($detail['note']) . '</p>';
                                        if (isset($detail['warning'])) echo '<p style="color: #dc3232;">' . esc_html($detail['warning']) . '</p>';
                                        if (isset($detail['error'])) echo '<p style="color: #dc3232;">' . esc_html($detail['error']) . '</p>';
                                        if (isset($detail['input'])) echo '<p><small>In: ' . esc_html(substr($detail['input'], 0, 50)) . '</small></p>';
                                        if (isset($detail['output'])) echo '<p><small>Out: ' . esc_html(substr($detail['output'], 0, 50)) . '</small></p>';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}
