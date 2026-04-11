<?php
/**
 * Plugin Name: Beiruttime OSINT Pro — نظام الرصد الاستخباراتي الموحد V17
 * Plugin URI: https://t.me/osint_lb
 * Description: V17.4.2 — الجيل الاحترافي الكامل مع قواميس موسعة: مركز قيادة استخباراتي [sod_command_deck]، بنوك المعلومات (أشخاص/أماكن/أسلحة/عمليات)، خوارزميات تصنيف متقدمة، رادار التهديد SVG، مخطط النشاط الساعي، رسم الكيانات والعلاقات، تتبع الساحات الساخنة، دعم كامل V11+V12+V16 في ملف واحد + 40+ فاعل عالمي + 30+ سلاح متطور.
 * Version: 17.4.2 Restructured
 * Author: Mohammad Qassem / Beirut Time
 * Author URI: https://t.me/osint_lb
 * Text Domain: beiruttime-osint-pro
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// تعريف ثوابت البرنامج الإضافي
define('BEIRUTTIME_OSINT_PRO_VERSION', '17.4.2');
define('BEIRUTTIME_OSINT_PRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BEIRUTTIME_OSINT_PRO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BEIRUTTIME_OSINT_PRO_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * التحميل التلقائي للفئات (PSR-4)
 */
spl_autoload_register(function ($class) {
    $prefix = 'Beiruttime\\OSINT\\';
    $base_dir = BEIRUTTIME_OSINT_PRO_PLUGIN_DIR . 'src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * دوال مساعدة للتوافق مع الإصدارات القديمة
 * سيتم إزالتها في الإصدارات المستقبلية
 */

// تحميل ملفات التوافق للخلفية (سيتم إزالتها في الإصدارات المستقبلية)
// تم نقل الوظائف إلى فئات معيارية في src/services/
// $compatibility_files = [
//     BEIRUTTIME_OSINT_PRO_PLUGIN_DIR . 'includes/classifier-service.php',
//     BEIRUTTIME_OSINT_PRO_PLUGIN_DIR . 'includes/newslog-service.php',
// ];

// foreach ($compatibility_files as $file) {
//     if (file_exists($file)) {
//         require_once $file;
//     }
// }

/**
 * تهيئة البرنامج الإضافي
 */
function beiruttime_osint_pro_init() {
    // تهيئة الفئات الرئيسية
    if (class_exists('Beiruttime\\OSINT\\Core\\Plugin')) {
        $plugin = Beiruttime\OSINT\Core\Plugin::getInstance();
        $plugin->run();
    }
    
    // تحميل نصوص الترجمة
    load_plugin_textdomain('beiruttime-osint-pro', false, dirname(BEIRUTTIME_OSINT_PRO_PLUGIN_BASENAME) . '/languages');
}
add_action('plugins_loaded', 'beiruttime_osint_pro_init');

/**
 * التفعيل
 */
function beiruttime_osint_pro_activate() {
    if (class_exists('Beiruttime\\OSINT\\Core\\Activation')) {
        Beiruttime\OSINT\Core\Activation::activate();
    }
    
    // حفظ إصدار البرنامج الإضافي
    update_option('beiruttime_osint_pro_version', BEIRUTTIME_OSINT_PRO_VERSION);
}
register_activation_hook(__FILE__, 'beiruttime_osint_pro_activate');

/**
 * التعطيل
 */
function beiruttime_osint_pro_deactivate() {
    if (class_exists('Beiruttime\\OSINT\\Core\\Deactivation')) {
        Beiruttime\OSINT\Core\Deactivation::deactivate();
    }
}
register_deactivation_hook(__FILE__, 'beiruttime_osint_pro_deactivate');

/**
 * إضافة رابط الإعدادات في صفحة البرامج الإضافية
 */
function beiruttime_osint_pro_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=beiruttime-osint-pro') . '">' . __('الإعدادات', 'beiruttime-osint-pro') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . BEIRUTTIME_OSINT_PRO_PLUGIN_BASENAME, 'beiruttime_osint_pro_add_settings_link');
