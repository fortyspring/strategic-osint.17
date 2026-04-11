<?php
/**
 * أدوات معالجة النصوص
 * 
 * دوال مساعدة لمعالجة وتنظيف النصوص
 * 
 * @package Beiruttime\OSINT\Utils
 */

namespace Beiruttime\OSINT\Utils;

/**
 * فئة TextUtils
 */
class TextUtils {
    
    /**
     * تنظيف النص من الأحرف غير المرغوب فيها
     * 
     * @param string $text النص المراد تنظيفه
     * @return string النص المنظف
     */
    public static function cleanText($text) {
        if (!is_string($text) || $text === '') {
            return '';
        }
        
        // إزالة المسافات الزائدة
        $text = trim($text);
        
        // توحيد المسافات
        $text = preg_replace('/\s+/', ' ', $text);
        
        // إزالة الأحرف الخاصة غير المرغوب فيها
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        
        return $text;
    }
    
    /**
     * تطبيع العنوان لإزالة التكرار
     * 
     * @param string $title العنوان
     * @return string العنوان المطعمع
     */
    public static function normalizeTitleForDedupe($title) {
        $title = self::cleanText($title);
        
        // تحويل الأرقام العربية إلى أرقام إنجليزية
        $arabic_numerals = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english_numerals = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $title = str_replace($arabic_numerals, $english_numerals, $title);
        
        // إزالة علامات الترقيم الزائدة
        $title = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title);
        
        // توحيد المسافات مرة أخرى
        $title = preg_replace('/\s+/', ' ', $title);
        
        return mb_strtolower($title);
    }
    
    /**
     * بناء بصمة للعنوان
     * 
     * @param string $text النص
     * @return string البصمة
     */
    public static function buildTitleFingerprint($text) {
        $text = self::normalizeTitleForDedupe($text);
        return md5($text);
    }
    
    /**
     * استخراج الكلمات المفتاحية من النص
     * 
     * @param string $text النص
     * @param int $limit الحد الأقصى للكلمات
     * @return array الكلمات المفتاحية
     */
    public static function extractKeywords($text, $limit = 10) {
        $text = self::cleanText($text);
        
        // تقسيم النص إلى كلمات
        $words = preg_split('/[\s,\.\!\?\:\;\(\)\[\]\"\'\-\—]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // كلمات توقف شائعة بالعربية
        $stopwords = [
            'في', 'من', 'على', 'إلى', 'عن', 'مع', 'لـ', 'ال', 'و', 'أو', 'ثم',
            'أن', 'إن', 'لكن', 'إذا', 'لو', 'ما', 'لا', 'لم', 'لن', 'كي', 'حتى',
            'هذا', 'هذه', 'ذلك', 'تلك', 'الذي', 'التي', 'الذين', 'اللواتي',
            'قد', 'سوف', 'يجب', 'يمكن', 'أيضاً', 'ايضا', 'بين', 'بينما', 'حيث',
        ];
        
        // تصفية الكلمات
        $keywords = [];
        foreach ($words as $word) {
            $word = mb_strtolower($word);
            if (mb_strlen($word) >= 3 && !in_array($word, $stopwords)) {
                $keywords[] = $word;
            }
        }
        
        // إزالة التكرار والحد من العدد
        $keywords = array_values(array_unique($keywords));
        return array_slice($keywords, 0, $limit);
    }
    
    /**
     * اقتطاع النص إلى طول معين مع إضافة نقاط
     * 
     * @param string $text النص
     * @param int $length الطول الأقصى
     * @return string النص المقتطع
     */
    public static function excerpt($text, $length = 100) {
        $text = self::cleanText($text);
        
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        
        return mb_substr($text, 0, $length) . '...';
    }
}
