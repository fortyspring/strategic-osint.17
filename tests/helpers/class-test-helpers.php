<?php
/**
 * Test Helpers
 * 
 * Utility functions for unit tests.
 */

class OSINT_Test_Helpers {
    
    /**
     * Create a test user
     */
    public static function create_user($role = 'subscriber') {
        return wp_create_user(
            'testuser_' . time(),
            'password123',
            'test@example.com'
        );
    }
    
    /**
     * Create a test post
     */
    public static function create_post($args = array()) {
        $defaults = array(
            'post_title' => 'Test Post ' . time(),
            'post_content' => 'Test content',
            'post_status' => 'publish',
            'post_type' => 'post',
        );
        
        $args = wp_parse_args($args, $defaults);
        return wp_insert_post($args);
    }
    
    /**
     * Mock an AJAX request
     */
    public static function mock_ajax_request($action, $data = array(), $user_id = null) {
        $_POST['action'] = $action;
        $_POST = array_merge($_POST, $data);
        
        if ($user_id) {
            wp_set_current_user($user_id);
        }
        
        // Set AJAX constant
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }
    }
    
    /**
     * Reset test environment
     */
    public static function reset() {
        $_POST = array();
        $_GET = array();
        $_REQUEST = array();
        
        // Clear transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
    }
}
