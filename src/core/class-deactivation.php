<?php
/**
 * فئة التعطيل
 * 
 * المسؤولة عن تنظيف البرنامج الإضافي عند التعطيل
 * 
 * @package Beiruttime\OSINT\Core
 */

namespace Beiruttime\OSINT\Core;

/**
 * فئة Deactivation
 */
class Deactivation {
    
    /**
     * تعطيل البرنامج الإضافي
     */
    public static function deactivate() {
        // إلغاء جدولة الأحداث cron
        self::unschedule_events();
        
        // إعادة كتابة القواعد
        flush_rewrite_rules();
    }
    
    /**
     * إلغاء جدولة الأحداث cron
     */
    private static function unschedule_events() {
        wp_clear_scheduled_hook('sod_daily_cleanup');
        wp_clear_scheduled_hook('sod_weekly_update');
    }
}
