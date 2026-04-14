<?php
/**
 * Beiruttime OSINT Pro - Bootstrap File
 * 
 * نقطة التحميل الرئيسية للنظام المعياري
 * يقوم بتحميل جميع المكونات بالترتيب الصحيح
 * 
 * @package Beiruttime\OSINT
 * @version 17.4.2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تحميل ملفات السمات (Traits) أولاً
 */
require_once __DIR__ . '/traits/trait-singleton.php';
require_once __DIR__ . '/traits/trait-loggable.php';

/**
 * تحميل وحدات الأمان والتنظيف
 */
require_once __DIR__ . '/security/class-security-loader.php';
osint_initialize_security();

/**
 * تحميل الخدمات الأساسية
 */
require_once __DIR__ . '/services/class-classifier.php';
require_once __DIR__ . '/services/class-newslog.php';
require_once __DIR__ . '/services/class-verification.php';
require_once __DIR__ . '/services/class-hybrid-warfare.php';
require_once __DIR__ . '/services/class-early-warning.php';
require_once __DIR__ . '/services/class-batch-reindexer.php';

/**
 * تحميل معالجات النظام
 */
require_once __DIR__ . '/handlers/class-smart-gatekeeper.php';
require_once __DIR__ . '/handlers/osint-hybrid-warfare-update.php';
require_once __DIR__ . '/handlers/osint-threat-radar.php';
require_once __DIR__ . '/handlers/osint-executive-reports.php';

/**
 * تحميل النواة الأساسية
 */
require_once __DIR__ . '/core/class-activation.php';
require_once __DIR__ . '/core/class-deactivation.php';
require_once __DIR__ . '/core/class-plugin.php';

/**
 * تشغيل البرنامج الإضافي
 */
$plugin = Beiruttime\OSINT\Core\Plugin::getInstance();
$plugin->run();
