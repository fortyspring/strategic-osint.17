<?php
/**
 * Security and Sanitization Utilities
 * 
 * دوال الأمان والتنظيف المستخرجة من الملف الرئيسي
 * 
 * @package Beiruttime\OSINT\Security
 */

if (!defined('ABSPATH')) {
    exit;
}

// تحميل دوال الأمان الأساسية
require_once __DIR__ . '/class-security-fixes.php';

/**
 * تنظيف قيمة بنك البيانات
 * 
 * @param mixed $value القيمة المراد تنظيفها
 * @return string القيمة المنظفة
 */
function osint_clean_bank_value($value): string {
    if ($value === null || $value === '') {
        return '';
    }
    
    $text = trim((string) $value);
    
    // إصلاح Mojibake
    if (sod_contains_mojibake_markers($text)) {
        $text = sod_fix_mojibake_text($text);
    }
    
    // تنظيف السلاسل الزائدة
    $text = preg_replace('/\s+/', ' ', $text);
    
    // إزالة الأحرف غير المطبوعة
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
    
    return sanitize_text_field($text);
}

/**
 * تطبيع قائمة من النصوص
 * 
 * @param array $values مصفوفة القيم
 * @return array مصفوفة القيم المطبعة
 */
function osint_normalize_string_list(array $values): array {
    $normalized = [];
    
    foreach ($values as $value) {
        $cleaned = osint_clean_bank_value($value);
        if ($cleaned !== '') {
            $normalized[] = $cleaned;
        }
    }
    
    return array_unique($normalized);
}

/**
 * تنظيف سجلات رسم الكيانات
 * 
 * @return int عدد السجلات التي تم تنظيفها
 */
function osint_clean_entity_graph_records(): int {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'osint_entity_graph';
    
    // التحقق من وجود الجدول
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
        return 0;
    }
    
    $count = 0;
    
    // تنظيف السجلات ذات القيم الفارغة
    $result = $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$table_name} WHERE entity_name = '' OR entity_type = ''"
        )
    );
    
    if ($result !== false) {
        $count += $result;
    }
    
    // تحديث السجلات ذات التنسيق الخاطئ
    $result = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$table_name} SET entity_name = TRIM(entity_name), entity_type = TRIM(entity_type) WHERE entity_name != TRIM(entity_name) OR entity_type != TRIM(entity_type)"
        )
    );
    
    if ($result !== false) {
        $count += $result;
    }
    
    return $count;
}

/**
 * تطبيع مفتاح البنك
 * 
 * @param string $key المفتاح
 * @return string المفتاح المطبع
 */
function osint_normalize_bank_key(string $key): string {
    // إزالة المسافات الزائدة
    $key = trim($key);
    
    // تحويل إلى أحرف صغيرة
    $key = strtolower($key);
    
    // استبدال المسافات بشرطات سفلية
    $key = preg_replace('/\s+/', '_', $key);
    
    // إزالة الأحرف الخاصة
    $key = preg_replace('/[^a-z0-9_]/', '', $key);
    
    return $key;
}

/**
 * التحقق من صحة قيمة بنك البيانات
 * 
 * @param string $canonical القيمة القانونية
 * @param string $value القيمة المراد التحقق منها
 * @return bool نتيجة التحقق
 */
function osint_is_valid_bank_value(string $canonical, string $value): bool {
    $canonical_normalized = osint_normalize_bank_key($canonical);
    $value_normalized = osint_normalize_bank_key($value);
    
    if ($canonical_normalized === $value_normalized) {
        return true;
    }
    
    // التحقق من التطابق الجزئي للأحرف العربية
    if (sod_has_arabic_chars($canonical) && sod_has_arabic_chars($value)) {
        // استخدام التطابق الجذري للعربية
        return sod_arabic_root_match($canonical, $value);
    }
    
    // التحقق من التشابه باستخدام Levenshtein
    $distance = levenshtein($canonical_normalized, $value_normalized);
    $max_length = max(strlen($canonical_normalized), strlen($value_normalized));
    
    if ($max_length > 0 && $distance / $max_length < 0.2) {
        return true;
    }
    
    return false;
}

/**
 * تصفية قيم بنك البيانات
 * 
 * @param array $values القيم المراد تصفيتها
 * @param string $canonical القيمة القانونية
 * @return array القيم المصفاة
 */
function osint_filter_bank_values(array $values, string $canonical): array {
    $filtered = [];
    
    foreach ($values as $value) {
        if (osint_is_valid_bank_value($canonical, $value)) {
            $filtered[] = osint_clean_bank_value($value);
        }
    }
    
    return array_unique($filtered);
}

/**
 * حساب طول النص بشكل آمن
 * 
 * @param mixed $text النص
 * @return int الطول
 */
function osint_safe_strlen($text): int {
    if ($text === null || $text === '') {
        return 0;
    }
    
    return mb_strlen((string) $text, 'UTF-8');
}

/**
 * استخراج جزء من النص بشكل آمن
 * 
 * @param mixed $text النص
 * @param int $start نقطة البداية
 * @param int $length الطول
 * @return string الجزء المستخرج
 */
function osint_safe_substr($text, int $start, int $length): string {
    if ($text === null || $text === '') {
        return '';
    }
    
    return mb_substr((string) $text, $start, $length, 'UTF-8');
}

/**
 * التحقق من وجود أحرف عربية
 * 
 * @param string $text النص
 * @return bool نتيجة التحقق
 */
function osint_has_arabic_chars(string $text): bool {
    return $text !== '' && preg_match('/\p{Arabic}/u', $text) === 1;
}

/**
 * إصلاح نص Mojibake
 * 
 * @param mixed $value القيمة
 * @return string النص المصلح
 */
function osint_fix_mojibake_text($value): string {
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }
    
    // قائمة بدائل Mojibake الشائعة
    $replacements = [
        'Ø§' => 'ا',
        'Ø¨' => 'ب',
        'Øª' => 'ت',
        'Ø«' => 'ث',
        'Ø¬' => 'ج',
        'Ø­' => 'ح',
        'Ø®' => 'خ',
        'Ø¯' => 'د',
        'Ø°' => 'ذ',
        'Ø±' => 'ر',
        'Ø²' => 'ز',
        'Ø³' => 'س',
        'Ø´' => 'ش',
        'Øµ' => 'ص',
        'Ø¶' => 'ض',
        'Ø·' => 'ط',
        'Ø¸' => 'ظ',
        'Ø¹' => 'ع',
        'Øº' => 'غ',
        'Ù' => 'ف',
        'Ù' => 'ق',
        'Ù' => 'ك',
        'Ù' => 'ل',
        'Ù' => 'م',
        'Ù' => 'ن',
        'Ù' => 'ه',
        'Ù' => 'و',
        'Ù' => 'ي',
        'Ù' => 'ً',
        'Ù' => 'ٍ',
        'Ù' => 'ٌ',
        'Ù' => 'َ',
        'Ù' => 'ُ',
        'Ù' => 'ِ',
        'Ù' => 'ّ',
        'Ù' => 'ْ',
    ];
    
    $text = strtr($text, $replacements);
    
    // محاولة إصلاح الترميز
    if (function_exists('mb_detect_encoding')) {
        $encoding = mb_detect_encoding($text, ['UTF-8', 'ISO-8859-6', 'Windows-1256'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $converted = mb_convert_encoding($text, 'UTF-8', $encoding);
            if ($converted !== false) {
                $text = $converted;
            }
        }
    }
    
    return $text;
}

/**
 * التحقق من وجود علامات Mojibake
 * 
 * @param string $text النص
 * @return bool نتيجة التحقق
 */
function osint_contains_mojibake_markers(string $text): bool {
    $markers = ['Ø', 'Ù', '¢', '£', '¥', '¦', '§', '¨', '©', 'ª', '«', '¬', '®', '¯'];
    
    foreach ($markers as $marker) {
        if (strpos($text, $marker) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * تنظيف وإصلاح خيارات قاعدة البيانات
 * 
 * @param mixed $value القيمة
 * @return mixed القيمة المصلحة
 */
function osint_repair_option_payload($value) {
    if (is_array($value)) {
        return array_map('osint_repair_option_payload', $value);
    }
    
    if (is_string($value)) {
        if (osint_contains_mojibake_markers($value)) {
            return osint_fix_mojibake_text($value);
        }
        return osint_clean_bank_value($value);
    }
    
    return $value;
}

/**
 * الشفاء الذاتي لخيارات Mojibake
 * 
 * @return void
 */
function osint_self_heal_mojibake_options(): void {
    $option_names = [
        'osint_actors_bank',
        'osint_targets_bank',
        'osint_weapons_bank',
        'osint_locations_bank',
        'osint_operations_bank',
    ];
    
    foreach ($option_names as $option_name) {
        $value = get_option($option_name);
        
        if ($value !== false) {
            $repaired = osint_repair_option_payload($value);
            
            if ($repaired !== $value) {
                update_option($option_name, $repaired, false);
                osint_log_security_event(
                    'option_repaired',
                    sprintf('تم إصلاح خيار %s من Mojibake', $option_name),
                    ['option_name' => $option_name]
                );
            }
        }
    }
}

/**
 * فرض تنظيف بنوك التعلم
 * 
 * @return void
 */
function osint_force_clean_learning_banks(): void {
    $bank_options = [
        'osint_actors_bank',
        'osint_targets_bank',
        'osint_weapons_bank',
        'osint_locations_bank',
        'osint_operations_bank',
        'osint_classified_events',
    ];
    
    foreach ($bank_options as $option_name) {
        $value = get_option($option_name);
        
        if (is_array($value)) {
            $cleaned = [];
            
            foreach ($value as $key => $item) {
                $cleaned_key = osint_normalize_bank_key($key);
                
                if (is_string($item)) {
                    $cleaned[$cleaned_key] = osint_clean_bank_value($item);
                } elseif (is_array($item)) {
                    $cleaned[$cleaned_key] = array_map('osint_clean_bank_value', $item);
                } else {
                    $cleaned[$cleaned_key] = $item;
                }
            }
            
            update_option($option_name, $cleaned, false);
        }
    }
    
    osint_log_security_event(
        'banks_cleaned',
        'تم فرض تنظيف بنوك التعلم',
        ['banks_count' => count($bank_options)]
    );
}

/**
 * إرسال رد JSON ناجح
 * 
 * @param mixed $data البيانات
 * @param int $status_code رمز الحالة
 * @return void
 */
function osint_send_json_success($data = null, int $status_code = 200): void {
    wp_send_json_success($data, $status_code);
}

/**
 * إرسال رد JSON خطأ
 * 
 * @param mixed $data البيانات
 * @param int|null $status_code رمز الحالة
 * @return void
 */
function osint_send_json_error($data = null, ?int $status_code = null): void {
    wp_send_json_error($data, $status_code);
}

/**
 * إعداد حمولة JSON
 * 
 * @param mixed $value القيمة
 * @return mixed الحمولة المعدة
 */
function osint_prepare_json_payload($value) {
    if (is_array($value)) {
        return array_map('osint_prepare_json_payload', $value);
    }
    
    if (is_string($value)) {
        // تنظيف النص
        $cleaned = osint_clean_bank_value($value);
        
        // التحقق من Mojibake
        if (osint_contains_mojibake_markers($cleaned)) {
            $cleaned = osint_fix_mojibake_text($cleaned);
        }
        
        return $cleaned;
    }
    
    return $value;
}

/**
 * الحصول على أعلام JSON آمنة
 * 
 * @return int أعلام JSON
 */
function osint_json_flags(): int {
    return JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;
}

/**
 * التحقق من صحة عنوان البريد الإلكتروني
 * 
 * @param string $email البريد الإلكتروني
 * @return bool نتيجة التحقق
 */
function osint_validate_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * التحقق من صحة URL
 * 
 * @param string $url العنوان
 * @return bool نتيجة التحقق
 */
function osint_validate_url(string $url): bool {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * توليد مفتاح API آمن
 * 
 * @return string مفتاح API
 */
function osint_generate_api_key(): string {
    return wp_generate_password(48, false, false);
}

/**
 * الحصول على عنوان IP العميل
 * 
 * @return string عنوان IP
 */
function osint_get_client_ip_address(): string {
    return osint_get_client_ip();
}

/**
 * تسجيل حدث أمني للتحقق من المدخلات
 * 
 * @param string $input_type نوع المدخلات
 * @param string $validation_result نتيجة التحقق
 * @param array $context السياق
 * @return void
 */
function osint_log_input_validation(string $input_type, string $validation_result, array $context = []): void {
    osint_log_security_event(
        'input_validation',
        sprintf('التحقق من %s: %s', $input_type, $validation_result),
        $context
    );
}
