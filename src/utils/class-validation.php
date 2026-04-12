<?php
/**
 * أدوات التحقق من البيانات
 * 
 * دوال مساعدة للتحقق من صحة البيانات
 * 
 * @package Beiruttime\OSINT\Utils
 */

namespace Beiruttime\OSINT\Utils;

/**
 * فئة Validation
 */
class Validation {
    
    /**
     * تحليل مصفوفة JSON
     * 
     * @param mixed $raw البيانات الخام
     * @return array المصفوفة المحللة
     */
    public static function parseJsonArray($raw) {
        if (is_array($raw)) {
            return $raw;
        }
        
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        
        $tmp = json_decode($raw, true);
        return is_array($tmp) ? $tmp : [];
    }
    
    /**
     * التحقق من أن النص ليس فارغاً
     * 
     * @param string $text النص
     * @return bool النتيجة
     */
    public static function isNotEmpty($text) {
        return is_string($text) && trim($text) !== '';
    }
    
    /**
     * التحقق من أن الرقم ضمن نطاق معين
     * 
     * @param int|float $value القيمة
     * @param int|float $min الحد الأدنى
     * @param int|float $max الحد الأقصى
     * @return bool النتيجة
     */
    public static function isInRange($value, $min, $max) {
        return is_numeric($value) && $value >= $min && $value <= $max;
    }
    
    /**
     * تنظيف وتحويل النص إلى سلسلة آمنة
     * 
     * @param string $text النص
     * @return string النص المنظف
     */
    public static function sanitizeString($text) {
        if (!is_string($text)) {
            return '';
        }
        
        return trim(strip_tags($text));
    }
    
    /**
     * التحقق من صحة معرف البريد الإلكتروني
     * 
     * @param string $email البريد الإلكتروني
     * @return bool النتيجة
     */
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * التحقق من صحة URL
     * 
     * @param string $url الرابط
     * @return bool النتيجة
     */
    public static function isValidUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * التحقق من أن المصفوفة تحتوي على مفاتيح معينة
     * 
     * @param array $array المصفوفة
     * @param array $keys المفاتيح المطلوبة
     * @return bool النتيجة
     */
    public static function hasKeys($array, $keys) {
        if (!is_array($array) || !is_array($keys)) {
            return false;
        }
        
        foreach ($keys as $key) {
            if (!array_key_exists($key, $array)) {
                return false;
            }
        }
        
        return true;
    }
}
