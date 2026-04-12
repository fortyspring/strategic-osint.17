<?php
/**
 * فئة التفعيل
 * 
 * المسؤولة عن إعداد البرنامج الإضافي عند التفعيل
 * 
 * @package Beiruttime\OSINT\Core
 */

namespace Beiruttime\OSINT\Core;

/**
 * فئة Activation
 */
class Activation {
    
    /**
     * تفعيل البرنامج الإضافي
     */
    public static function activate() {
        // إنشاء جداول قاعدة البيانات المخصصة
        self::create_database_tables();
        
        // تعيين الخيارات الافتراضية
        self::set_default_options();
        
        // إعادة كتابة القواعد (Flush rewrite rules)
        flush_rewrite_rules();
        
        // جدولة الأحداث cron
        self::schedule_events();
    }
    
    /**
     * إنشاء جداول قاعدة البيانات المخصصة
     */
    private static function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // جدول سجل الأحداث
        $table_events = $wpdb->prefix . 'sod_events';
        $sql_events = "CREATE TABLE IF NOT EXISTS $table_events (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title text NOT NULL,
            content longtext,
            event_date datetime DEFAULT CURRENT_TIMESTAMP,
            actor varchar(255) DEFAULT '',
            target varchar(255) DEFAULT '',
            weapon varchar(255) DEFAULT '',
            region varchar(255) DEFAULT '',
            intel_type varchar(50) DEFAULT '',
            tactical_level varchar(50) DEFAULT '',
            score int(11) DEFAULT 0,
            war_data longtext,
            field_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_date (event_date),
            KEY actor (actor),
            KEY region (region),
            KEY intel_type (intel_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_events);
        
        // جدول بنوك المعلومات
        $table_banks = $wpdb->prefix . 'sod_banks';
        $sql_banks = "CREATE TABLE IF NOT EXISTS $table_banks (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            bank_type varchar(50) NOT NULL,
            term varchar(255) NOT NULL,
            weight decimal(3,2) DEFAULT 1.00,
            keywords text,
            context_words text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY bank_type (bank_type),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        dbDelta($sql_banks);
    }
    
    /**
     * تعيين الخيارات الافتراضية
     */
    private static function set_default_options() {
        $defaults = [
            'sod_version' => BEIRUTTIME_OSINT_PRO_VERSION,
            'sod_auto_train_enabled' => 1,
            'sod_auto_eval_enabled' => 1,
            'sod_batch_size' => 100,
            'sod_confidence_threshold' => 60,
            'sod_bank_actors' => [],
            'sod_bank_targets' => [],
            'sod_bank_contexts' => [],
            'sod_bank_intents' => [],
            'sod_bank_weapons' => [],
        ];
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
    
    /**
     * جدولة الأحداث cron
     */
    private static function schedule_events() {
        // حدث التنظيف اليومي
        if (!wp_next_scheduled('sod_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'sod_daily_cleanup');
        }
        
        // حدث التحديث الأسبوعي
        if (!wp_next_scheduled('sod_weekly_update')) {
            wp_schedule_event(time(), 'weekly', 'sod_weekly_update');
        }
    }
}
