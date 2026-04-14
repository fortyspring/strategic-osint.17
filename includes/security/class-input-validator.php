<?php
/**
 * Input Validation and Sanitization Service
 * 
 * خدمة التحقق من المدخلات وتنظيفها
 * 
 * @package Beiruttime\OSINT\Security
 */

if (!defined('ABSPATH')) {
    exit;
}

class OSINT_Input_Validator {
    
    /**
     * قائمة أنواع الملفات المسموحة
     * 
     * @var array
     */
    private static $allowed_mime_types = [
        'json' => 'application/json',
        'csv' => 'text/csv',
        'txt' => 'text/plain',
        'xml' => 'application/xml',
    ];
    
    /**
     * الحد الأقصى لحجم الملف (5 ميجابايت افتراضياً)
     * 
     * @var int
     */
    private static $max_file_size = 5242880;
    
    /**
     * التحقق من ملف مرفوع
     * 
     * @param array $file بيانات الملف
     * @param array $options خيارات التحقق
     * @return array|WP_Error
     */
    public static function validate_upload($file, $options = []) {
        $allowed_types = $options['allowed_types'] ?? array_values(self::$allowed_mime_types);
        $max_size = $options['max_size'] ?? self::$max_file_size;
        
        return osint_validate_uploaded_file($file, $allowed_types, $max_size);
    }
    
    /**
     * تنظيف مدخلات النص
     * 
     * @param string $input النص المدخل
     * @param array $options خيارات التنظيف
     * @return string النص المنظف
     */
    public static function sanitize_text($input, $options = []) {
        if ($input === null || $input === '') {
            return $options['default'] ?? '';
        }
        
        $type = $options['type'] ?? 'text';
        return osint_sanitize_input($input, $type, $options);
    }
    
    /**
     * تطبيع نص عربي
     * 
     * @param string $text النص
     * @return string النص المطبع
     */
    public static function normalize_arabic_text($text) {
        if (empty($text)) {
            return '';
        }
        
        // إصلاح Mojibake إذا وجد
        if (osint_contains_mojibake_markers($text)) {
            $text = osint_fix_mojibake_text($text);
        }
        
        // توحيد الألف
        $text = preg_replace('/[أإآ]/u', 'ا', $text);
        
        // توحيد الياء
        $text = preg_replace('/[ىي]/u', 'ي', $text);
        
        // توحيد الهاء والتاء المربوطة
        $text = preg_replace('/[هة]/u', 'ه', $text);
        
        // إزالة التشكيل
        $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);
        
        // إزالة المسافات الزائدة
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    /**
     * التحقق من صحة JSON
     * 
     * @param string $json_string نص JSON
     * @return array|WP_Error مصفوفة البيانات أو خطأ
     */
    public static function validate_json($json_string) {
        if (empty($json_string)) {
            return new WP_Error('empty_json', 'نص JSON فارغ');
        }
        
        $decoded = json_decode($json_string, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_messages = [
                JSON_ERROR_DEPTH => 'الحد الأقصى للعمق تم تجاوزه',
                JSON_ERROR_STATE_MISMATCH => 'تباين في الحالة أو سوء تشكيل',
                JSON_ERROR_CTRL_CHAR => 'حرف تحكم غير متوقع',
                JSON_ERROR_SYNTAX => 'خطأ في صياغة JSON',
                JSON_ERROR_UTF8 => 'سوء تشكيل أحرف UTF-8',
            ];
            
            $message = $error_messages[json_last_error()] ?? 'JSON غير صالح';
            return new WP_Error('invalid_json', $message);
        }
        
        return $decoded;
    }
    
    /**
     * التحقق من صحة عنوان URL
     * 
     * @param string $url العنوان
     * @param array $options خيارات إضافية
     * @return string|WP_Error العنوان الصالح أو خطأ
     */
    public static function validate_url($url, $options = []) {
        if (empty($url)) {
            return new WP_Error('empty_url', 'عنوان URL فارغ');
        }
        
        $allowed_protocols = $options['protocols'] ?? ['http', 'https'];
        $sanitized = filter_var($url, FILTER_SANITIZE_URL);
        
        if (!filter_var($sanitized, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'عنوان URL غير صالح');
        }
        
        $parsed = parse_url($sanitized);
        
        if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], $allowed_protocols)) {
            return new WP_Error('invalid_protocol', 'بروتوكول غير مسموح به');
        }
        
        return esc_url_raw($sanitized);
    }
    
    /**
     * التحقق من صحة البريد الإلكتروني
     * 
     * @param string $email البريد الإلكتروني
     * @return string|WP_Error البريد الصالح أو خطأ
     */
    public static function validate_email($email) {
        if (empty($email)) {
            return new WP_Error('empty_email', 'البريد الإلكتروني فارغ');
        }
        
        $sanitized = sanitize_email($email);
        
        if (!is_email($sanitized)) {
            return new WP_Error('invalid_email', 'البريد الإلكتروني غير صالح');
        }
        
        return $sanitized;
    }
    
    /**
     * التحقق من صحة رقم الهاتف
     * 
     * @param string $phone رقم الهاتف
     * @param string $country_code رمز الدولة
     * @return string|WP_Error الرقم الصالح أو خطأ
     */
    public static function validate_phone($phone, $country_code = 'US') {
        if (empty($phone)) {
            return new WP_Error('empty_phone', 'رقم الهاتف فارغ');
        }
        
        // إزالة جميع الأحرف غير الرقمية
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);
        
        // التحقق من الطول mínimo
        if (strlen($cleaned) < 8) {
            return new WP_Error('invalid_phone', 'رقم الهاتف قصير جداً');
        }
        
        // التحقق من الطول máximo
        if (strlen($cleaned) > 15) {
            return new WP_Error('invalid_phone', 'رقم الهاتف طويل جداً');
        }
        
        return $cleaned;
    }
    
    /**
     * التحقق من CSRF Token
     * 
     * @param string $nonce الـ Nonce
     * @param string $action اسم الإجراء
     * @return bool نتيجة التحقق
     */
    public static function verify_nonce($nonce, $action = -1) {
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * التحقق من الصلاحيات
     * 
     * @param string $capability الصلاحية المطلوبة
     * @param int $user_id معرف المستخدم
     * @return bool نتيجة التحقق
     */
    public static function check_capability($capability, $user_id = null) {
        if ($user_id === null) {
            return current_user_can($capability);
        }
        
        return user_can($user_id, $capability);
    }
    
    /**
     * التحقق من Rate Limiting
     * 
     * @param string $action اسم الإجراء
     * @param int $limit عدد الطلبات المسموحة
     * @param int $window النافذة الزمنية
     * @return bool نتيجة التحقق
     */
    public static function check_rate_limit($action, $limit = 100, $window = 3600) {
        return osint_check_rate_limit($action, $limit, $window);
    }
    
    /**
     * تسجيل محاولة وصول غير مصرح بها
     * 
     * @param string $reason سبب الرفض
     * @param array $context السياق
     * @return void
     */
    public static function log_access_denied($reason, $context = []) {
        osint_log_security_event(
            'access_denied',
            $reason,
            array_merge([
                'ip' => osint_get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ], $context)
        );
    }
    
    /**
     * التحقق من صحة مصفوفة
     * 
     * @param array $data المصفوفة
     * @param array $schema مخطط التحقق
     * @return array|WP_Error المصفوفة الصالحة أو خطأ
     */
    public static function validate_array($data, $schema) {
        if (!is_array($data)) {
            return new WP_Error('invalid_type', 'البيانات يجب أن تكون مصفوفة');
        }
        
        $validated = [];
        
        foreach ($schema as $key => $rules) {
            $value = $data[$key] ?? null;
            
            if (isset($rules['required']) && $rules['required'] && $value === null) {
                return new WP_Error(
                    'missing_required_field',
                    sprintf('الحقل %s مطلوب', $key)
                );
            }
            
            if ($value === null) {
                if (isset($rules['default'])) {
                    $validated[$key] = $rules['default'];
                }
                continue;
            }
            
            // التحقق من النوع
            if (isset($rules['type'])) {
                $type_valid = false;
                
                switch ($rules['type']) {
                    case 'string':
                        $type_valid = is_string($value);
                        break;
                    case 'int':
                    case 'integer':
                        $type_valid = is_int($value) || ctype_digit((string)$value);
                        break;
                    case 'float':
                        $type_valid = is_float($value) || is_numeric($value);
                        break;
                    case 'bool':
                    case 'boolean':
                        $type_valid = is_bool($value);
                        break;
                    case 'array':
                        $type_valid = is_array($value);
                        break;
                    case 'email':
                        $type_valid = is_email($value);
                        break;
                    case 'url':
                        $type_valid = filter_var($value, FILTER_VALIDATE_URL) !== false;
                        break;
                }
                
                if (!$type_valid) {
                    return new WP_Error(
                        'invalid_type',
                        sprintf('الحقل %s يجب أن يكون من نوع %s', $key, $rules['type'])
                    );
                }
            }
            
            // التحقق من القيم المسموحة
            if (isset($rules['allowed_values']) && is_array($rules['allowed_values'])) {
                if (!in_array($value, $rules['allowed_values'], true)) {
                    return new WP_Error(
                        'invalid_value',
                        sprintf('قيمة %s غير مسموحة للحقل %s', $value, $key)
                    );
                }
            }
            
            // التحقق من الطول الأدنى والأقصى للنصوص
            if (is_string($value)) {
                if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
                    return new WP_Error(
                        'too_short',
                        sprintf('الحقل %s يجب أن يكون %d أحرف على الأقل', $key, $rules['min_length'])
                    );
                }
                
                if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
                    return new WP_Error(
                        'too_long',
                        sprintf('الحقل %s يجب ألا يتجاوز %d حرف', $key, $rules['max_length'])
                    );
                }
            }
            
            // التحقق من القيم الرقمية
            if (is_numeric($value)) {
                if (isset($rules['min']) && $value < $rules['min']) {
                    return new WP_Error(
                        'too_small',
                        sprintf('الحقل %s يجب أن يكون %d على الأقل', $key, $rules['min'])
                    );
                }
                
                if (isset($rules['max']) && $value > $rules['max']) {
                    return new WP_Error(
                        'too_large',
                        sprintf('الحقل %s يجب ألا يتجاوز %d', $key, $rules['max'])
                    );
                }
            }
            
            // التنظيف
            if (isset($rules['sanitize'])) {
                $value = call_user_func($rules['sanitize'], $value);
            }
            
            $validated[$key] = $value;
        }
        
        return $validated;
    }
}
