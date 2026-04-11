<?php
/**
 * فئة البرنامج الإضافي الرئيسية
 * 
 * المسؤولة عن تهيئة وتشغيل جميع مكونات النظام
 * 
 * @package Beiruttime\OSINT\Core
 */

namespace Beiruttime\OSINT\Core;

/**
 * فئة Plugin
 */
class Plugin {
    
    /**
     * النسخة الوحيدة من الفئة
     * 
     * @var Plugin|null
     */
    private static $instance = null;
    
    /**
     * إصدار البرنامج الإضافي
     * 
     * @var string
     */
    const VERSION = '17.4.2';
    
    /**
     * الحصول على النسخة الوحيدة من الفئة (Singleton Pattern)
     * 
     * @return Plugin
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * منع إنشاء نسخ متعددة
     */
    private function __construct() {
        $this->define_constants();
        $this->init_hooks();
    }
    
    /**
     * منع الاستنساخ
     */
    private function __clone() {}
    
    /**
     * منع إعادة التسلسل
     */
    public function __wakeup() {
        throw new \Exception('Cannot unserialize singleton');
    }
    
    /**
     * تعريف الثوابت الأساسية
     */
    private function define_constants() {
        if (!defined('BEIRUTTIME_OSINT_PRO_VERSION')) {
            define('BEIRUTTIME_OSINT_PRO_VERSION', self::VERSION);
        }
    }
    
    /**
     * ربط الخطافات (Hooks)
     */
    private function init_hooks() {
        // سيتم إضافة الخطافات هنا
    }
    
    /**
     * تشغيل البرنامج الإضافي
     */
    public function run() {
        // تهيئة الخدمات
        $this->init_services();
        
        // تهيئة واجهة الإدارة
        if (is_admin()) {
            $this->init_admin();
        }
        
        // تهيئة الواجهة الأمامية
        $this->init_frontend();
    }
    
    /**
     * تهيئة الخدمات الأساسية
     */
    private function init_services() {
        // تهيئة خدمات التصنيف
        if (class_exists('Beiruttime\\OSINT\\Services\\Classifier')) {
            // Classifier::getInstance();
        }
        
        // تهيئة خدمات سجل الأخبار
        if (class_exists('Beiruttime\\OSINT\\Services\\Newslog')) {
            // Newslog::getInstance();
        }
    }
    
    /**
     * تهيئة واجهة الإدارة
     */
    private function init_admin() {
        // تهيئة قائمة الإدارة
        if (class_exists('Beiruttime\\OSINT\\Admin\\AdminMenu')) {
            // AdminMenu::getInstance();
        }
        
        // تهيئة صفحات الإدارة
        if (class_exists('Beiruttime\\OSINT\\Admin\\AdminPages')) {
            // AdminPages::getInstance();
        }
        
        // تهيئة معالجات AJAX
        if (class_exists('Beiruttime\\OSINT\\Admin\\AjaxHandlers')) {
            // AjaxHandlers::getInstance();
        }
    }
    
    /**
     * تهيئة الواجهة الأمامية
     */
    private function init_frontend() {
        // تهيئة الرموز القصيرة
        if (class_exists('Beiruttime\\OSINT\\Frontend\\Shortcodes')) {
            // Shortcodes::getInstance();
        }
        
        // تهيئة تحميل الأصول
        if (class_exists('Beiruttime\\OSINT\\Frontend\\Assets')) {
            // Assets::getInstance();
        }
    }
}
