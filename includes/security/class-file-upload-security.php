<?php
/**
 * File Upload Security Handler
 * 
 * Provides secure file upload validation and handling for Beiruttime OSINT Pro.
 * Replaces direct $_FILES usage with validated, secure methods.
 * 
 * @package Beiruttime_OSINT_Pro
 * @subpackage Security
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

class OSINT_File_Upload_Security {
    
    /**
     * Allowed MIME types for settings import
     */
    const ALLOWED_SETTINGS_MIME = ['application/json', 'text/plain'];
    
    /**
     * Allowed MIME types for CSV bank imports
     */
    const ALLOWED_CSV_MIME = ['text/csv', 'application/vnd.ms-excel', 'text/plain'];
    
    /**
     * Maximum file size for settings (2MB)
     */
    const MAX_SETTINGS_SIZE = 2097152;
    
    /**
     * Maximum file size for CSV imports (5MB)
     */
    const MAX_CSV_SIZE = 5242880;
    
    /**
     * Validate and handle uploaded file securely
     * 
     * @param array $file The $_FILES array element
     * @param string $type File type: 'settings' or 'csv'
     * @return array|WP_Error Validated file data or WP_Error on failure
     */
    public static function validate_uploaded_file($file, $type = 'settings') {
        // Check if file exists in request
        if (empty($file) || !is_array($file)) {
            return new WP_Error('no_file', __('لم يتم رفع أي ملف.', 'beiruttime-osint-pro'));
        }
        
        // Check upload error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return self::get_upload_error($file['error']);
        }
        
        // Validate file size
        $max_size = ($type === 'settings') ? self::MAX_SETTINGS_SIZE : self::MAX_CSV_SIZE;
        if ($file['size'] > $max_size) {
            return new WP_Error(
                'file_too_large',
                sprintf(__('حجم الملف كبير جداً. الحد الأقصى: %d ميجابايت.', 'beiruttime-osint-pro'), $max_size / 1048576)
            );
        }
        
        // Validate MIME type
        $allowed_mimes = ($type === 'settings') ? self::ALLOWED_SETTINGS_MIME : self::ALLOWED_CSV_MIME;
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $file['tmp_name']);
        finfo_close($file_info);
        
        if (!in_array($mime_type, $allowed_mimes, true)) {
            return new WP_Error(
                'invalid_mime_type',
                sprintf(__('نوع الملف غير مسموح. الأنواع المسموحة: %s', 'beiruttime-osint-pro'), implode(', ', $allowed_mimes))
            );
        }
        
        // Additional validation for JSON files
        if ($type === 'settings') {
            $content = file_get_contents($file['tmp_name']);
            if ($content === false) {
                return new WP_Error('read_error', __('تعذرت قراءة محتوى الملف.', 'beiruttime-osint-pro'));
            }
            
            $decoded = json_decode($content, true);
            if (!is_array($decoded) || !isset($decoded['_plugin'])) {
                return new WP_Error(
                    'invalid_json',
                    __('الملف غير صالح — يرجى استخدام ملف JSON من هذه الإضافة فقط.', 'beiruttime-osint-pro')
                );
            }
            
            return [
                'tmp_name' => $file['tmp_name'],
                'content' => $content,
                'decoded' => $decoded,
                'size' => $file['size'],
                'mime_type' => $mime_type
            ];
        }
        
        // Additional validation for CSV files
        if ($type === 'csv') {
            $handle = fopen($file['tmp_name'], 'r');
            if (!$handle) {
                return new WP_Error('read_error', __('تعذرت قراءة ملف CSV.', 'beiruttime-osint-pro'));
            }
            
            // Check for BOM and header
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }
            
            $header = fgetcsv($handle);
            fclose($handle);
            
            if (!is_array($header) || count($header) < 3) {
                return new WP_Error('invalid_csv', __('ملف CSV غير صالح - يجب أن يحتوي على ترويسة وأعمدة.', 'beiruttime-osint-pro'));
            }
            
            return [
                'tmp_name' => $file['tmp_name'],
                'size' => $file['size'],
                'mime_type' => $mime_type,
                'has_bom' => ($bom === "\xEF\xBB\xBF"),
                'header' => $header
            ];
        }
        
        return new WP_Error('invalid_type', __('نوع الملف غير معروف.', 'beiruttime-osint-pro'));
    }
    
    /**
     * Get human-readable upload error message
     * 
     * @param int $error_code PHP upload error code
     * @return WP_Error
     */
    private static function get_upload_error($error_code) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => __('الملف أكبر من الحد المسموح في php.ini.', 'beiruttime-osint-pro'),
            UPLOAD_ERR_FORM_SIZE => __('الملف أكبر من الحد المسموح في نموذج الرفع.', 'beiruttime-osint-pro'),
            UPLOAD_ERR_PARTIAL => __('تم رفع جزء من الملف فقط.', 'beiruttime-osint-pro'),
            UPLOAD_ERR_NO_FILE => __('لم يتم رفع أي ملف.', 'beiruttime-osint-pro'),
            UPLOAD_ERR_NO_TMP_DIR => __('مجلد مؤقت مفقود.', 'beiruttime-osint-pro'),
            UPLOAD_ERR_CANT_WRITE => __('فشل الكتابة على القرص.', 'beiruttime-osint-pro'),
            UPLOAD_ERR_EXTENSION => __('توقف رفع الملف بواسطة إضافة PHP.', 'beiruttime-osint-pro')
        ];
        
        $message = $messages[$error_code] ?? __('خطأ غير معروف في رفع الملف.', 'beiruttime-osint-pro');
        return new WP_Error('upload_error', $message);
    }
    
    /**
     * Securely process settings import
     * 
     * @param array $validated_file Validated file data from validate_uploaded_file()
     * @return array|WP_Error Import result or error
     */
    public static function import_settings($validated_file) {
        if (!is_array($validated_file) || !isset($validated_file['decoded'])) {
            return new WP_Error('invalid_data', __('بيانات الملف غير صالحة.', 'beiruttime-osint-pro'));
        }
        
        $decoded = $validated_file['decoded'];
        $allowed = array_keys(self::get_all_plugin_options());
        $restored = 0;
        $skipped = 0;
        
        foreach ($decoded as $key => $value) {
            if ($key === '_plugin' || $key === '_exported_at') {
                continue;
            }
            
            if (in_array($key, $allowed, true)) {
                // Sanitize based on option type
                $sanitized_value = self::sanitize_option_value($key, $value);
                update_option($key, $sanitized_value);
                $restored++;
            } else {
                $skipped++;
            }
        }
        
        return [
            'success' => true,
            'restored' => $restored,
            'skipped' => $skipped,
            'message' => sprintf(__('تم استعادة %d إعداد وتخطي %d.', 'beiruttime-osint-pro'), $restored, $skipped)
        ];
    }
    
    /**
     * Sanitize option value based on key
     * 
     * @param string $key Option key
     * @param mixed $value Option value
     * @return mixed Sanitized value
     */
    private static function sanitize_option_value($key, $value) {
        // List of options that should be arrays
        $array_options = [
            'so_sources', 'so_tg_sources', 'so_x_sources', 'so_video_streams',
            'so_filter_keywords', 'so_exec_reports_custom_hours'
        ];
        
        // List of options that should be integers
        $int_options = [
            'so_alert_threshold', 'so_popup_threshold', 'so_popup_recent_minutes',
            'so_fetch_latest_only', 'so_enable_logging', 'so_popup_enabled',
            'so_llm_brief_enabled', 'so_telegram_enabled', 'so_exec_reports_enabled',
            'so_exec_reports_force_repeat', 'so_exec_reports_frequency',
            'so_exec_reports_window_hours', 'so_exec_reports_critical_threshold',
            'sod_powerbi_default_hours', 'sod_powerbi_auto_refresh'
        ];
        
        // List of options that should be floats
        $float_options = [];
        
        // List of options that should be booleans
        $bool_options = [
            'so_enable_logging', 'so_fetch_latest_only', 'so_popup_enabled',
            'so_llm_brief_enabled', 'so_telegram_enabled', 'so_exec_reports_enabled',
            'so_exec_reports_force_repeat', 'sod_powerbi_auto_refresh'
        ];
        
        if (in_array($key, $bool_options, true)) {
            return (bool) $value;
        }
        
        if (in_array($key, $int_options, true)) {
            return (int) $value;
        }
        
        if (in_array($key, $float_options, true)) {
            return (float) $value;
        }
        
        if (in_array($key, $array_options, true)) {
            if (!is_array($value)) {
                return [];
            }
            // Recursively sanitize array values
            return array_map(function($v) use ($key) {
                if (is_string($v)) {
                    return sanitize_text_field($v);
                }
                return $v;
            }, $value);
        }
        
        // Default: sanitize as text
        if (is_string($value)) {
            return sanitize_text_field($value);
        }
        
        return $value;
    }
    
    /**
     * Get all plugin option keys
     * 
     * @return array List of option keys
     */
    private static function get_all_plugin_options() {
        return [
            'so_enable_logging' => false,
            'so_fetch_latest_only' => false,
            'so_filter_keywords' => [],
            'so_openweather_api_key' => '',
            'so_twitter_bearer_token' => '',
            'so_alert_threshold' => 150,
            'so_popup_enabled' => true,
            'so_popup_threshold' => 180,
            'so_popup_recent_minutes' => 30,
            'so_popup_telegram_link' => '',
            'so_alert_sound_url' => '',
            'so_sources' => [],
            'so_tg_sources' => [],
            'so_x_sources' => [],
            'so_llm_api_key' => '',
            'so_llm_brief_enabled' => false,
            'so_tg_token' => '',
            'so_tg_chat' => '',
            'so_telegram_enabled' => false,
            'so_discord_webhook' => '',
            'so_exec_reports_frequency' => 'daily',
            'so_exec_reports_window_hours' => 24,
            'so_exec_reports_critical_threshold' => 200,
            'so_exec_reports_chat_id' => '',
            'so_exec_reports_footer' => '',
            'so_exec_reports_custom_hours' => [],
            'so_exec_reports_enabled' => false,
            'so_exec_reports_force_repeat' => false,
            'so_custom_primary_color' => '#1a73e8',
            'so_custom_bg_color' => '#ffffff',
            'so_custom_font' => 'Arial',
            'so_video_streams' => [],
            'so_external_cron_key' => '',
            'sod_powerbi_default_hours' => 24,
            'sod_powerbi_auto_refresh' => false
        ];
    }
    
    /**
     * Process CSV bank import
     * 
     * @param array $validated_file Validated CSV file data
     * @return array|WP_Error Import statistics or error
     */
    public static function import_banks_csv($validated_file) {
        global $wpdb;
        
        if (!is_array($validated_file) || !isset($validated_file['tmp_name'])) {
            return new WP_Error('invalid_data', __('بيانات ملف CSV غير صالحة.', 'beiruttime-osint-pro'));
        }
        
        $handle = fopen($validated_file['tmp_name'], 'r');
        if (!$handle) {
            return new WP_Error('read_error', __('تعذرت قراءة ملف CSV.', 'beiruttime-osint-pro'));
        }
        
        // Handle BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        
        // Skip header row
        fgetcsv($handle);
        
        $imported = 0;
        $skipped = 0;
        $valid_banks = ['people', 'places', 'weapons', 'operations'];
        
        while (($cols = fgetcsv($handle)) !== false) {
            if (count($cols) < 3) {
                $skipped++;
                continue;
            }
            
            $bank = sanitize_key(trim($cols[0]));
            $name = sanitize_text_field(trim($cols[1]));
            $affil = sanitize_text_field(trim($cols[2]));
            $notes = sanitize_text_field(trim($cols[3] ?? ''));
            $weight = isset($cols[4]) ? abs((float)$cols[4]) : 5.0;
            
            if (!in_array($bank, $valid_banks, true) || empty($name)) {
                $skipped++;
                continue;
            }
            
            $key = $bank . ':' . $name;
            $hash = md5($key);
            $ts = time();
            
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}so_entity_graph WHERE edge_hash=%s",
                $hash
            ));
            
            if ($exists) {
                $wpdb->update(
                    "{$wpdb->prefix}so_entity_graph",
                    [
                        'target_name' => $affil,
                        'region' => $notes,
                        'weight' => $weight,
                        'last_seen' => $ts
                    ],
                    ['edge_hash' => $hash]
                );
            } else {
                $wpdb->insert(
                    "{$wpdb->prefix}so_entity_graph",
                    [
                        'edge_hash' => $hash,
                        'actor_name' => $key,
                        'target_name' => $affil,
                        'region' => $notes,
                        'weight' => $weight,
                        'event_count' => 0,
                        'first_seen' => $ts,
                        'last_seen' => $ts
                    ]
                );
            }
            
            $imported++;
        }
        
        fclose($handle);
        
        return [
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'message' => sprintf(__('تم استيراد %d سجل وتخطي %d.', 'beiruttime-osint-pro'), $imported, $skipped)
        ];
    }
}
