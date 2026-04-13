<?php
/**
 * OSINT Pro - Modular Architecture Base
 * 
 * This file initializes the modular structure for the OSINT plugin.
 * It replaces the monolithic approach with a clean, maintainable architecture.
 * 
 * @package BeirutTime_OSINT_Pro
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OSINT_Modular_Core {
    
    private static $instance = null;
    private $modules = array();
    private $cache_handler = null;
    private $websocket_handler = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize modular system
     */
    public function __construct() {
        $this->load_dependencies();
        $this->init_cache();
        $this->init_modules();
        $this->init_websocket();
        $this->register_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        $files = array(
            'cache/class-cache-handler.php',
            'websocket/class-websocket-handler.php',
            'modules/class-module-interface.php',
            'modules/class-base-module.php',
            'handlers/class-data-handler.php',
            'handlers/class-api-handler.php',
            'services/class-telegram-service.php',
            'services/class-analysis-service.php',
        );
        
        foreach ($files as $file) {
            $path = OSINT_PRO_PLUGIN_DIR . 'includes/' . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
    
    /**
     * Initialize cache system
     */
    private function init_cache() {
        if (class_exists('OSINT_Cache_Handler')) {
            $this->cache_handler = OSINT_Cache_Handler::get_instance();
        }
    }
    
    /**
     * Initialize modules
     */
    private function init_modules() {
        $module_classes = array(
            'OSINT_Dashboard_Module',
            'OSINT_Map_Module',
            'OSINT_Chart_Module',
            'OSINT_Analysis_Module',
            'OSINT_Export_Module',
        );
        
        foreach ($module_classes as $class) {
            if (class_exists($class)) {
                $module = new $class($this);
                if ($module->is_active()) {
                    $this->modules[$module->get_id()] = $module;
                    $module->init();
                }
            }
        }
    }
    
    /**
     * Initialize WebSocket for real-time updates
     */
    private function init_websocket() {
        if (class_exists('OSINT_WebSocket_Handler')) {
            $this->websocket_handler = OSINT_WebSocket_Handler::get_instance();
        }
    }
    
    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        add_action('wp_ajax_osint_module_action', array($this, 'handle_module_ajax'));
        add_action('wp_ajax_nopriv_osint_module_action', array($this, 'handle_module_ajax'));
        
        // Cache clearing on post update
        add_action('save_post', array($this, 'clear_related_cache'));
        
        // Shutdown hook for performance
        add_action('shutdown', array($this, 'cleanup'));
    }
    
    /**
     * Handle module AJAX requests
     */
    public function handle_module_ajax() {
        check_ajax_referer('osint_nonce', 'nonce');
        
        $module_id = sanitize_text_field($_POST['module_id'] ?? '');
        $action = sanitize_text_field($_POST['action_type'] ?? '');
        
        if (empty($module_id) || !isset($this->modules[$module_id])) {
            wp_send_json_error(array('message' => 'Invalid module'));
            return;
        }
        
        $module = $this->modules[$module_id];
        $result = $module->handle_ajax($action, $_POST);
        
        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * Clear cache related to updated content
     */
    public function clear_related_cache($post_id) {
        if ($this->cache_handler) {
            $this->cache_handler->clear_related($post_id);
        }
    }
    
    /**
     * Cleanup on shutdown
     */
    public function cleanup() {
        if ($this->cache_handler) {
            $this->cache_handler->cleanup();
        }
    }
    
    /**
     * Get module by ID
     */
    public function get_module($id) {
        return $this->modules[$id] ?? null;
    }
    
    /**
     * Get all active modules
     */
    public function get_modules() {
        return $this->modules;
    }
    
    /**
     * Get cache handler
     */
    public function get_cache() {
        return $this->cache_handler;
    }
    
    /**
     * Get WebSocket handler
     */
    public function get_websocket() {
        return $this->websocket_handler;
    }
}

// Initialize the modular core
function osint_init_modular() {
    return OSINT_Modular_Core::get_instance();
}

// Auto-initialize if not disabled
if (!defined('OSINT_DISABLE_MODULAR') || !OSINT_DISABLE_MODULAR) {
    add_action('plugins_loaded', 'osint_init_modular', 5);
}
