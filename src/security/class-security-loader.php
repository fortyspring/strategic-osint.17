<?php
/**
 * Security Autoloader and Initializer
 * 
 * محمل تلقائي ومهيء لوحدات الأمان
 * 
 * @package Beiruttime\OSINT\Security
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تحميل جميع ملفات الأمان
 */
function osint_load_security_modules() {
    $security_dir = __DIR__;
    
    // تحميل دوال الأمان الأساسية أولاً
    require_once $security_dir . '/class-security-fixes.php';
    
    // تحميل دوال التنظيف
    require_once $security_dir . '/class-sanitization-utils.php';
    
    // تحميل مدقق المدخلات
    require_once $security_dir . '/class-input-validator.php';
    
    // تحميل أمان رفع الملفات
    require_once $security_dir . '/class-file-upload-security.php';
}

/**
 * تهيئة نظام الأمان
 */
function osint_initialize_security() {
    // تحميل الوحدات
    osint_load_security_modules();
    
    // تعريف الثوابت الأمنية
    if (!defined('OSINT_SECURITY_LOGGING')) {
        define('OSINT_SECURITY_LOGGING', true);
    }
    
    if (!defined('OSINT_RATE_LIMIT_DEFAULT')) {
        define('OSINT_RATE_LIMIT_DEFAULT', 100);
    }
    
    if (!defined('OSINT_RATE_LIMIT_WINDOW')) {
        define('OSINT_RATE_LIMIT_WINDOW', 3600);
    }
    
    if (!defined('OSINT_MAX_UPLOAD_SIZE')) {
        define('OSINT_MAX_UPLOAD_SIZE', 5242880); // 5MB
    }
    
    // تشغيل الإصلاحات التلقائية
    add_action('init', 'osint_run_security_maintenance');
    
    // تسجيل أحداث الأمان عند تفعيل الإضافة
    // ملاحظة: سيتم استدعاؤها من الملف الرئيسي
    // register_activation_hook(__DIR__ . '/../../beiruttime-osint-pro.php', 'osint_setup_security_logging');
    
    // إضافة إجراءات AJAX الآمنة
    add_action('wp_ajax_osint_verify_security', function() {
        osint_verify_ajax_request('osint_security_check', true, 'read');
        wp_send_json_success([
            'status' => 'secure',
            'timestamp' => current_time('mysql'),
            'ip' => osint_get_client_ip(),
        ]);
    });
    
    // تسجيل حدث عند تسجيل الدخول الفاشل
    add_action('wp_login_failed', function($username) {
        $ip = osint_get_client_ip();
        
        // تسجيل الحدث
        osint_log_security_event(
            'login_failed',
            sprintf('محاولة دخول فاشلة للمستخدم: %s', $username),
            ['username' => $username, 'ip' => $ip]
        );
        
        // تتبع المحاولات
        $failed_attempts = get_option('osint_failed_login_attempts', []);
        if (!is_array($failed_attempts)) {
            $failed_attempts = [];
        }
        
        $failed_attempts[] = [
            'username' => $username,
            'ip' => $ip,
            'timestamp' => time(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ];
        
        // الاحتفاظ بآخر 100 محاولة فقط
        if (count($failed_attempts) > 100) {
            $failed_attempts = array_slice($failed_attempts, -100);
        }
        
        update_option('osint_failed_login_attempts', $failed_attempts, false);
        
        // زيادة العداد
        $count = (int) get_option('osint_failed_login_count', 0);
        update_option('osint_failed_login_count', $count + 1, false);
    });
    
    // تسجيل حدث عند تسجيل الدخول الناجح
    add_action('wp_login', function($user_login, $user) {
        osint_log_security_event(
            'login_success',
            sprintf('تسجيل دخول ناجح للمستخدم: %s', $user_login),
            [
                'user_id' => $user->ID,
                'username' => $user_login,
                'roles' => $user->roles,
            ]
        );
    }, 10, 2);
}

/**
 * تشغيل صيانة الأمان الدورية
 */
function osint_run_security_maintenance() {
    // التحقق من خيارات Mojibake مرة واحدة يومياً
    $last_check = get_option('osint_security_maintenance_last', 0);
    
    if (time() - $last_check > DAY_IN_SECONDS) {
        // إصلاح خيارات Mojibake
        osint_self_heal_mojibake_options();
        
        // تنظيف سجلات الكيانات
        osint_clean_entity_graph_records();
        
        // تحديث وقت آخر فحص
        update_option('osint_security_maintenance_last', time(), false);
    }
}

/**
 * إعداد سجلات الأمان عند التفعيل
 */
function osint_setup_security_logging() {
    // إنشاء مجلد السجلات إذا لم يكن موجوداً
    $log_dir = WP_CONTENT_DIR . '/uploads/osint-logs';
    
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
        
        // إنشاء ملف .htaccess لحماية المجلد
        $htaccess_content = "Order deny,allow\nDeny from all\n";
        file_put_contents($log_dir . '/.htaccess', $htaccess_content);
    }
    
    // تعيين القيم الافتراضية
    add_option('osint_failed_login_count', 0);
    add_option('osint_blocked_ips_count', 0);
    add_option('osint_suspicious_requests_count', 0);
    add_option('osint_failed_login_attempts', []);
    
    // جدولة الأحداث الدورية
    if (!wp_next_scheduled('osint_daily_security_cleanup')) {
        wp_schedule_event(time(), 'daily', 'osint_daily_security_cleanup');
    }
}

/**
 * تنظيف أمني يومي
 */
function osint_daily_security_cleanup() {
    // تنظيف السجلات القديمة (أقدم من 30 يوم)
    $log_file = WP_CONTENT_DIR . '/uploads/osint-security.log';
    
    if (file_exists($log_file)) {
        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $cutoff_time = strtotime('-30 days');
        
        $filtered_lines = [];
        foreach ($lines as $line) {
            // استخراج التاريخ من السجل
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2})/', $line, $matches)) {
                $line_time = strtotime($matches[1]);
                if ($line_time > $cutoff_time) {
                    $filtered_lines[] = $line;
                }
            }
        }
        
        // إعادة كتابة الملف مع السجلات الحديثة فقط
        if (count($filtered_lines) !== count($lines)) {
            file_put_contents($log_file, implode("\n", $filtered_lines) . "\n");
        }
    }
    
    // تنظيف محاولات تسجيل الدخول القديمة
    $failed_attempts = get_option('osint_failed_login_attempts', []);
    if (is_array($failed_attempts)) {
        $cutoff_time = time() - DAY_IN_SECONDS;
        $filtered_attempts = array_filter($failed_attempts, function($timestamp) use ($cutoff_time) {
            return $timestamp > $cutoff_time;
        });
        update_option('osint_failed_login_attempts', array_values($filtered_attempts), false);
    }
}

// ملاحظة: لا تستدعِ osint_initialize_security() هنا
// يجب استدعاؤها من الملف الرئيسي بعد تحميل جميع الملفات
