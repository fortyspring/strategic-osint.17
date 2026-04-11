<?php
/**
 * سمة Singleton
 * 
 * توفر نمط التصميم Singleton للفئات
 * 
 * @package Beiruttime\OSINT\Traits
 */

namespace Beiruttime\OSINT\Traits;

/**
 * سمة Singleton
 */
trait Singleton {
    
    /**
     * النسخة الوحيدة من الفئة
     * 
     * @var self|null
     */
    private static $instance = null;
    
    /**
     * الحصول على النسخة الوحيدة من الفئة
     * 
     * @return self
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
    private function __construct() {}
    
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
}
