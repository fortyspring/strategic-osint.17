<?php
/**
 * Security Fixes Implementation
 * 
 * تطبيق الإصلاحات الأمنية للمستودع
 * 
 * @package Beiruttime\OSINT\Security
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * التحقق من نوع وحجم الملف المرفوع
 * 
 * @param array $file بيانات الملف من $_FILES
 * @param array $allowed_types أنواع الملفات المسموحة
 * @param int $max_size الحد الأقصى للحجم بالبايت
 * @return array|WP_Error مصفوفة بنتيجة التحقق أو WP_Error في حالة الفشل
 */
function osint_validate_uploaded_file($file, $allowed_types = ['application/json', 'text/csv', 'text/plain'], $max_size = 5242880) {
    if (!isset($file) || !is_array($file)) {
        return new WP_Error('no_file', 'لم يتم رفع أي ملف');
    }
    
    // التحقق من وجود خطأ في الرفع
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'حجم الملف يتجاوز الحد المسموح في إعدادات PHP',
            UPLOAD_ERR_FORM_SIZE => 'حجم الملف يتجاوز الحد المسموح في النموذج',
            UPLOAD_ERR_PARTIAL => 'تم رفع جزء من الملف فقط',
            UPLOAD_ERR_NO_FILE => 'لم يتم رفع أي ملف',
            UPLOAD_ERR_NO_TMP_DIR => 'مجلد مؤقت مفقود',
            UPLOAD_ERR_CANT_WRITE => 'فشل الكتابة على القرص',
            UPLOAD_ERR_EXTENSION => 'توقف الرفع بسبب إضافة PHP',
        ];
        
        $message = $error_messages[$file['error']] ?? 'خطأ غير معروف في رفع الملف';
        return new WP_Error('upload_error', $message);
    }
    
    // التحقق من حجم الملف
    if ($file['size'] > $max_size) {
        $max_size_mb = round($max_size / 1024 / 1024, 2);
        return new WP_Error('file_too_large', sprintf('حجم الملف يتجاوز الحد المسموح (%s ميجابايت)', $max_size_mb));
    }
    
    // التحقق من نوع الملف باستخدام finfo
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types, true)) {
            return new WP_Error('invalid_file_type', sprintf('نوع الملف غير مسموح به. الأنواع المسموحة: %s', implode(', ', $allowed_types)));
        }
    } else {
        // Fallback: التحقق من امتداد الملف
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = [];
        
        foreach ($allowed_types as $type) {
            switch ($type) {
                case 'application/json':
                    $allowed_extensions[] = 'json';
                    break;
                case 'text/csv':
                    $allowed_extensions[] = 'csv';
                    break;
                case 'text/plain':
                    $allowed_extensions[] = 'txt';
                    break;
            }
        }
        
        if (!in_array($extension, $allowed_extensions, true)) {
            return new WP_Error('invalid_file_extension', 'امتداد الملف غير مسموح به');
        }
    }
    
    // التحقق من محتوى الملف JSON إذا كان نوعه JSON
    if (in_array('application/json', $allowed_types, true)) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension === 'json') {
            $content = file_get_contents($file['tmp_name']);
            json_decode($content);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error('invalid_json', 'ملف JSON غير صالح');
            }
        }
    }
    
    return ['success' => true, 'file' => $file];
}

/**
 * Rate Limiting للطلبات
 * 
 * @param string $action اسم الإجراء للتحكم في المعدل
 * @param int $limit عدد الطلبات المسموحة
 * @param int $window النافذة الزمنية بالثواني
 * @return bool صحيح إذا كان مسموحاً، خطأ إذا تم تجاوز الحد
 */
function osint_check_rate_limit($action, $limit = 100, $window = 3600) {
    // الحصول على معرف فريد للمستخدم/العنوان IP
    if (is_user_logged_in()) {
        $identifier = 'user_' . get_current_user_id();
    } else {
        $identifier = 'ip_' . osint_get_client_ip();
    }
    
    $key = 'osint_rate_' . md5($action . '_' . $identifier);
    $count = (int) get_transient($key);
    
    if ($count >= $limit) {
        // تسجيل محاولة تجاوز الحد
        error_log(sprintf(
            '[OSINT Rate Limit] Action: %s, Identifier: %s, Limit: %d exceeded',
            $action,
            $identifier,
            $limit
        ));
        return false;
    }
    
    set_transient($key, $count + 1, $window);
    return true;
}

/**
 * الحصول على عنوان IP العميل بشكل آمن
 * 
 * @return string عنوان IP
 */
function osint_get_client_ip() {
    $ip_keys = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];
    
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = explode(',', $_SERVER[$key])[0];
            $ip = trim($ip);
            
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return sanitize_text_field($ip);
            }
        }
    }
    
    return '0.0.0.0';
}

/**
 * تعزيز AJAX Security
 * 
 * @param string $nonce_action اسم إجراء Nonce
 * @param bool $require_auth هل يتطلب مصادقة المستخدم
 * @param string $capability الصلاحية المطلوبة
 * @return void|WP_Error يرسل خطأ JSON في حالة الفشل
 */
function osint_verify_ajax_request($nonce_action, $require_auth = true, $capability = 'read') {
    // التحقق من Nonce (إلزامي للجميع)
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    
    if (empty($nonce) || wp_verify_nonce($nonce, $nonce_action) === false) {
        wp_send_json_error(['message' => 'خطأ في التحقق من الأمان'], 403);
    }
    
    // التحقق من المصادقة إذا لزم الأمر
    if ($require_auth && !is_user_logged_in()) {
        wp_send_json_error(['message' => 'يجب تسجيل الدخول'], 401);
    }
    
    // التحقق من الصلاحيات إذا لزم الأمر
    if ($require_auth && !current_user_can($capability)) {
        wp_send_json_error(['message' => 'غير مصرح لك بتنفيذ هذا الإجراء'], 403);
    }
    
    // التحقق من Rate Limiting
    $action_key = str_replace('wp_ajax_', '', current_filter());
    if (!osint_check_rate_limit($action_key, 60, 60)) { // 60 طلب في الدقيقة
        wp_send_json_error(['message' => 'تجاوزت حد الطلبات المسموحة. يرجى الانتظار'], 429);
    }
}

/**
 * تنظيف وتصفية المدخلات بشكل آمن
 * 
 * @param mixed $input البيانات المدخلة
 * @param string $type نوع البيانات المتوقعة
 * @param array $options خيارات إضافية
 * @return mixed البيانات المنظفة
 */
function osint_sanitize_input($input, $type = 'text', $options = []) {
    if ($input === null) {
        return $options['default'] ?? null;
    }
    
    switch ($type) {
        case 'text':
            return sanitize_text_field(wp_unslash($input));
            
        case 'textarea':
            return sanitize_textarea_field(wp_unslash($input));
            
        case 'url':
            return esc_url_raw(wp_unslash($input));
            
        case 'email':
            return sanitize_email(wp_unslash($input));
            
        case 'int':
        case 'integer':
            return intval($input);
            
        case 'float':
            return floatval($input);
            
        case 'bool':
        case 'boolean':
            return (bool) $input;
            
        case 'array':
            if (!is_array($input)) {
                return $options['default'] ?? [];
            }
            
            $callback = $options['sanitize_callback'] ?? 'sanitize_text_field';
            return array_map($callback, $input);
            
        case 'html':
            $allowed_tags = $options['allowed_tags'] ?? [
                'p' => [],
                'br' => [],
                'strong' => [],
                'em' => [],
                'a' => ['href' => [], 'title' => []],
            ];
            return wp_kses(wp_unslash($input), $allowed_tags);
            
        default:
            return sanitize_text_field(wp_unslash($input));
    }
}

/**
 * تسجيل حدث أمني
 * 
 * @param string $event_type نوع الحدث الأمني
 * @param string $message رسالة الوصف
 * @param array $context سياق إضافي
 * @return void
 */
function osint_log_security_event($event_type, $message, $context = []) {
    $log_entry = [
        'timestamp' => current_time('mysql'),
        'event_type' => $event_type,
        'message' => $message,
        'context' => $context,
        'user_id' => get_current_user_id(),
        'username' => is_user_logged_in() ? wp_get_current_user()->user_login : 'guest',
        'ip_address' => osint_get_client_ip(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
    ];
    
    // حفظ في جدول اللوج أو ملف
    if (defined('OSINT_SECURITY_LOGGING') && OSINT_SECURITY_LOGGING) {
        $log_file = WP_CONTENT_DIR . '/uploads/osint-security.log';
        $log_line = sprintf(
            "[%s] %s | User: %s | IP: %s | %s\n",
            $log_entry['timestamp'],
            $log_entry['event_type'],
            $log_entry['username'],
            $log_entry['ip_address'],
            $log_entry['message']
        );
        
        file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
    }
    
    // يمكن أيضاً الحفظ في جدول قاعدة بيانات مخصص
    do_action('osint_security_event_logged', $log_entry);
}

/**
 * منع XSS في المخرجات
 * 
 * @param string $data البيانات
 * @param string $context سياق الاستخدام
 * @return string البيانات المعالجة
 */
function osint_escape_output($data, $context = 'html') {
    switch ($context) {
        case 'html':
            return esc_html($data);
            
        case 'attr':
            return esc_attr($data);
            
        case 'url':
            return esc_url($data);
            
        case 'js':
            return esc_js($data);
            
        case 'textarea':
            return esc_textarea($data);
            
        default:
            return esc_html($data);
    }
}

/**
 * التحقق من CSRF Token للنماذج
 * 
 * @param string $field_name اسم حقل Nonce
 * @param string $action اسم الإجراء
 * @return bool نتيجة التحقق
 */
function osint_verify_form_nonce($field_name = '_wpnonce', $action = -1) {
    if (!isset($_POST[$field_name])) {
        return false;
    }
    
    return wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$field_name])), $action);
}

/**
 * إنشاء تقرير أمني
 * 
 * @return array التقرير الأمني
 */
function osint_generate_security_report() {
    global $wpdb;
    
    $report = [
        'generated_at' => current_time('mysql'),
        'metrics' => [
            'failed_login_attempts' => (int) get_option('osint_failed_login_count', 0),
            'blocked_ips' => (int) get_option('osint_blocked_ips_count', 0),
            'suspicious_requests' => (int) get_option('osint_suspicious_requests_count', 0),
        ],
        'recent_events' => [],
        'recommendations' => [],
    ];
    
    // تحليل الأحداث الأخيرة
    $last_24h = time() - DAY_IN_SECONDS;
    
    // التحقق من محاولات الاختراق
    $failed_logins = get_option('osint_failed_login_attempts', []);
    if (is_array($failed_logins)) {
        $recent_failures = array_filter($failed_logins, function($timestamp) use ($last_24h) {
            return $timestamp > $last_24h;
        });
        
        if (count($recent_failures) > 10) {
            $report['recommendations'][] = 'تم رصد أكثر من 10 محاولات فاشلة لتسجيل الدخول في آخر 24 ساعة. يوصى بتفعيل الحماية الإضافية.';
        }
    }
    
    return $report;
}

// تطبيق الإصلاحات على الوظائف الحالية
add_action('init', function() {
    // تعريف ثوابت الأمان
    if (!defined('OSINT_SECURITY_LOGGING')) {
        define('OSINT_SECURITY_LOGGING', true);
    }
    
    if (!defined('OSINT_RATE_LIMIT_DEFAULT')) {
        define('OSINT_RATE_LIMIT_DEFAULT', 100);
    }
    
    if (!defined('OSINT_RATE_LIMIT_WINDOW')) {
        define('OSINT_RATE_LIMIT_WINDOW', 3600);
    }
});
