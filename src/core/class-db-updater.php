<?php
/**
 * Beiruttime OSINT - Database Schema Updater
 * 
 * يقوم هذا الملف بإضافة الأعمدة الجديدة اللازمة لمنطق الحرب المركبة إلى جدول الأخبار.
 * يتم استدعاؤه تلقائياً عند تفعيل الإضافة أو يمكن تشغيله يدوياً.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Beiruttime_OSINT_DB_Updater {

    public static function update_schema() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'so_news_events';
        $charset_collate = $wpdb->get_charset_collate();

        // قائمة التعديلات المطلوبة
        $columns_to_add = [
            'hybrid_layers' => "JSON NULL COMMENT 'طبقات الحرب المركبة النشطة في هذا الخبر'",
            'hybrid_combinations' => "JSON NULL COMMENT 'التوليفات المركبة المكتشفة'",
            'verification_status' => "VARCHAR(50) DEFAULT 'unverified' COMMENT 'حالة التحقق'",
            'confidence_score' => "DECIMAL(3,2) DEFAULT 0.00 COMMENT 'درجة الثقة (0-1)'",
            'threat_score' => "INT DEFAULT 0 COMMENT 'مؤشر التهديد (0-100)'",
            'sentiment_score' => "DECIMAL(3,2) DEFAULT 0.00 COMMENT 'مؤشر المشاعر'",
            'actor_network' => "JSON NULL COMMENT 'شبكة الفاعلين المرتبطة'",
            'early_warning_flag' => "TINYINT(1) DEFAULT 0 COMMENT 'علم الإنذار المبكر'",
            'warning_level' => "VARCHAR(20) DEFAULT 'none' COMMENT 'مستوى التحذير'",
            'processed_at' => "DATETIME NULL COMMENT 'وقت معالجة الخبر بالنظام الجديد'"
        ];

        $changes_made = false;

        foreach ($columns_to_add as $column_name => $definition) {
            // التحقق من وجود العمود
            $exists = $wpdb->get_var($wpdb->prepare(
                "SHOW COLUMNS FROM {$table_name} LIKE %s",
                $column_name
            ));

            if (!$exists) {
                // إضافة العمود إذا لم يكن موجوداً
                $sql = "ALTER TABLE {$table_name} ADD COLUMN {$column_name} {$definition}";
                $wpdb->query($sql);
                
                if ($wpdb->last_error) {
                    error_log("OSINT DB Error adding column {$column_name}: " . $wpdb->last_error);
                } else {
                    $changes_made = true;
                    error_log("OSINT DB: Column {$column_name} added successfully.");
                }
            }
        }

        // إنشاء جداول مساعدة للإنذار المبكر إذا لم تكن موجودة
        self::create_warning_tables();

        return $changes_made;
    }

    private static function create_warning_tables() {
        global $wpdb;
        
        $warning_table = $wpdb->prefix . 'so_early_warnings';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$warning_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            news_id bigint(20) UNSIGNED NOT NULL,
            warning_type varchar(50) NOT NULL,
            severity_level varchar(20) NOT NULL,
            description text NOT NULL,
            recommendation text NULL,
            is_resolved tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            resolved_at datetime NULL,
            PRIMARY KEY  (id),
            KEY news_id (news_id),
            KEY severity_level (severity_level)
        ) {$wpdb->get_charset_collate()};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// تشغيل التحديث مباشرة عند تضمين الملف (للاختبار) أو ربطه بالـ Activation Hook
// Beiruttime_OSINT_DB_Updater::update_schema();
