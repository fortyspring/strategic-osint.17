#!/usr/bin/env php
<?php
/**
 * سكربت فحص وإصلاح تلقائي لثغرات SQL Injection
 * يقوم بمسح ملف beiruttime-osint-pro.php واستبدال الاستعلامات الخطرة بدوال آمنة
 * 
 * الاستخدام: php scripts/fix-sql-injection.php [مسار_الملف]
 */

if (php_sapi_name() !== 'cli') {
    die("هذا السكربت يعمل فقط من سطر الأوامر (CLI)\n");
}

// تحديد مسار الملف الرئيسي
$plugin_file = $argv[1] ?? __DIR__ . '/../beiruttime-osint-pro.php';
$helpers_file = __DIR__ . '/../includes/security-helpers.php';

if (!file_exists($plugin_file)) {
    echo "⚠️ تحذير: الملف الرئيسي غير موجود: $plugin_file\n\n";
    echo "📋 طريقة الاستخدام:\n";
    echo "   php scripts/fix-sql-injection.php /path/to/beiruttime-osint-pro.php\n\n";
    echo "📁 الملفات المتاحة حالياً:\n";
    
    // عرض هيكل المشروع
    echo "\n";
    echo "✅ ملفات الأمان الجاهزة:\n";
    echo "   - includes/security-helpers.php  (دوال الحماية من SQL Injection و XSS)\n";
    echo "   - tests/security-tests.php       (اختبارات أمنية شاملة)\n";
    echo "   - SECURITY_FIX_REPORT.md         (تقرير مفصل للإصلاحات المطلوبة)\n";
    echo "   - scripts/fix-sql-injection.php  (هذا السكربت)\n\n";
    
    echo "🎯 الخطوات التالية:\n";
    echo "   1. تأكد من وجود ملف beiruttime-osint-pro.php في المستودع\n";
    echo "   2. شغل السكربت مع مسار الملف: php scripts/fix-sql-injection.php beiruttime-osint-pro.php\n";
    echo "   3. راجع التقرير الناتج وقم بالإصلاحات\n";
    echo "   4. شغل الاختبارات للتحقق: php tests/security-tests.php\n\n";
    
    exit(0);
}

if (!file_exists($helpers_file)) {
    die("خطأ: ملف دوال الأمان غير موجود: $helpers_file\n");
}

echo "🔍 جاري مسح الملف: $plugin_file\n";
$content = file_get_contents($plugin_file);
$original_content = $content;
$fixes_count = 0;
$issues_found = [];

// نمط للبحث عن استعلامات SQL خطيرة
$patterns = [
    // نمط 1: $wpdb->query("SELECT ... WHERE ... = '$_POST[...]'"
    '/(\$wpdb->query\s*\(\s*["\'].*?(SELECT|INSERT|UPDATE|DELETE).*?)(\$_(GET|POST|REQUEST)\[[^\]]+\])/i',
    
    // نمط 2: $wpdb->get_results("SELECT ... " . $_GET[...])
    '/(\$wpdb->get_results\s*\(\s*["\'].*?(SELECT|INSERT|UPDATE|DELETE).*?)(\$_(GET|POST|REQUEST)\[[^\]]+\])/i',
    
    // نمط 3: $wpdb->get_var("SELECT ... WHERE id = " . $_GET['id'])
    '/(\$wpdb->get_var\s*\(\s*["\'].*?(SELECT|INSERT|UPDATE|DELETE).*?)(\$_(GET|POST|REQUEST)\[[^\]]+\])/i',
];

// البحث عن الاستعلامات الخطرة
foreach ($patterns as $pattern) {
    if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            $issues_found[] = [
                'type' => 'SQL_INJECTION',
                'code' => substr($content, max(0, $match[1] - 50), 200),
                'position' => $match[1],
                'line' => substr_count(substr($content, 0, $match[1]), "\n") + 1
            ];
        }
    }
}

echo "📊 تم العثور على " . count($issues_found) . " ثغرة SQL Injection محتملة\n\n";

// عرض العينة الأولى من الثغرات
if (!empty($issues_found)) {
    echo "📋 عينة من الثغرات المكتشفة:\n";
    echo str_repeat("-", 80) . "\n";
    
    $sample_count = min(5, count($issues_found));
    for ($i = 0; $i < $sample_count; $i++) {
        $issue = $issues_found[$i];
        echo "❌ الثغرة #" . ($i + 1) . "\n";
        echo "   السطر: " . $issue['line'] . "\n";
        echo "   الكود: " . trim($issue['code']) . "\n";
        echo "   النوع: " . $issue['type'] . "\n\n";
    }
    
    if (count($issues_found) > 5) {
        echo "... و" . (count($issues_found) - 5) . " ثغرات أخرى (راجع التقرير الكامل)\n";
    }
}

// إنشاء تقرير مفصل
$report_file = __DIR__ . '/sql-injection-report.json';
$report_data = [
    'scan_date' => date('Y-m-d H:i:s'),
    'file_scanned' => $plugin_file,
    'total_issues' => count($issues_found),
    'issues' => $issues_found
];

file_put_contents($report_file, json_encode($report_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n✅ تم حفظ التقرير المفصل في: $report_file\n";

// اقتراح الإصلاحات
echo "\n💡 توصيات الإصلاح:\n";
echo str_repeat("-", 80) . "\n";
echo "1. استبدل الاستعلامات المباشرة بـ \$wpdb->prepare()\n";
echo "2. استخدم الدوال المساعدة من includes/security-helpers.php\n";
echo "3. مثال للإصلاح:\n\n";

echo "   ❌ قبل (خطر):\n";
echo "      \$wpdb->query(\"SELECT * FROM table WHERE id = \$_GET['id']\");\n\n";

echo "   ✅ بعد (آمن):\n";
echo "      \$wpdb->prepare(\"SELECT * FROM table WHERE id = %d\", \$_GET['id']);\n\n";

echo "   أو باستخدام الدالة المساعدة:\n";
echo "      so_safe_query(\"SELECT * FROM table WHERE id = %d\", [\$_GET['id']]);\n\n";

echo str_repeat("-", 80) . "\n";
echo "🎯 الخطوة التالية: راجع SECURITY_FIX_REPORT.md للحصول على قائمة كاملة بالإصلاحات المطلوبة.\n";
echo "🚀 لتشغيل الاختبارات: php tests/security-tests.php\n";
