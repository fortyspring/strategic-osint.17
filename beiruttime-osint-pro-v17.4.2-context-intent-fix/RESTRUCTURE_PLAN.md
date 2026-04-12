# Beiruttime OSINT Pro - إعادة هيكلة المشروع

## 📋 نظرة عامة على إعادة الهيكلة

تم إعادة هيكلة المشروع لتحويله من ملف PHP واحد ضخم (14,759 سطر) إلى بنية معيارية احترافية تتبع أفضل الممارسات.

## 🏗️ البنية الجديدة

```
beiruttime-osint-pro/
├── beiruttime-osint-pro.php          # الملف الرئيسي القديم (للتوافق)
├── beiruttime-osint-pro-restructured.php  # الملف الرئيسي الجديد
├── README.txt                        # وثائق التثبيت
├── RESTRUCTURE_PLAN.md               # خطة إعادة الهيكلة (هذا الملف)
├── languages/                        # ملفات الترجمة
├── templates/                        # قوالب HTML/PHP
│   └── admin/                        # قوالب لوحة الإدارة
├── assets/                           # الأصول الثابتة
│   ├── css/                          # ملفات التنسيق
│   │   └── admin-pages.css
│   └── js/                           # ملفات JavaScript
│       ├── db-admin.js
│       └── newslog-admin.js
├── includes/                         # ملفات التوافق القديمة
│   ├── classifier-service.php
│   └── newslog-service.php
└── src/                              # كود المصدر المعياري الجديد
    ├── core/                         # النواة الأساسية
    │   ├── class-plugin.php          # فئة البرنامج الإضافي الرئيسية ✅
    │   ├── class-activation.php      # فئة التفعيل ✅
    │   └── class-deactivation.php    # فئة التعطيل ✅
    ├── services/                     # خدمات الأعمال
    │   ├── class-classifier.php      # خدمة التصنيف (قيد الإنشاء)
    │   ├── class-newslog.php         # خدمة سجل الأخبار (قيد الإنشاء)
    │   └── class-evaluation.php      # خدمة التقييم (قيد الإنشاء)
    ├── admin/                        # واجهة الإدارة (فارغ حالياً)
    ├── frontend/                     # واجهة المستخدم الأمامية (فارغ حالياً)
    ├── utils/                        # أدوات مساعدة
    │   ├── class-text-utils.php      # أدوات معالجة النصوص ✅
    │   ├── class-matching.php        # خوارزميات المطابقة (قيد الإنشاء)
    │   └── class-validation.php      # التحقق من البيانات ✅
    └── traits/                       # سمات PHP القابلة لإعادة الاستخدام
        ├── trait-singleton.php       # نمط Singleton ✅
        └── trait-loggable.php        # التسجيل ✅
```

## ✅ الملفات المكتملة

### النواة الأساسية (Core)
- [x] `src/core/class-plugin.php` - فئة Plugin الرئيسية
- [x] `src/core/class-activation.php` - فئة التفعيل
- [x] `src/core/class-deactivation.php` - فئة التعطيل

### السمات (Traits)
- [x] `src/traits/trait-singleton.php` - نمط Singleton
- [x] `src/traits/trait-loggable.php` - وظائف التسجيل

### الأدوات المساعدة (Utils)
- [x] `src/utils/class-text-utils.php` - معالجة النصوص
- [x] `src/utils/class-validation.php` - التحقق من البيانات

### الملفات الرئيسية
- [x] `beiruttime-osint-pro-restructured.php` - الملف الرئيسي الجديد
- [x] `RESTRUCTURE_PLAN.md` - خطة التوثيق

## 🔄 التغييرات الرئيسية

### 1. **فصل الاهتمامات (Separation of Concerns)**
- تم فصل منطق الأعمال عن واجهة الإدارة
- تم عزل وظائف المساعدة في فئات مستقلة
- تم تنظيم الخدمات حسب الوظيفة

### 2. **التحميل التلقائي (Autoloading)**
```php
// سيتم استخدام PSR-4 autoloading
spl_autoload_register(function ($class) {
    $prefix = 'Beiruttime\\OSINT\\';
    $base_dir = __DIR__ . '/src/';
    
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
```

### 3. **إعادة تسمية الدوال**
| الدالة القديمة | الفئة/الملف الجديد |
|----------------|-------------------|
| `sod_get_all_banks()` | `\Beiruttime\OSINT\Services\Classifier::getAllBanks()` |
| `sod_match_bank()` | `\Beiruttime\OSINT\Utils\Matching::matchBank()` |
| `sod_detect_action_type()` | `\Beiruttime\OSINT\Services\Classifier::detectActionType()` |
| `sod_parse_json_array()` | `\Beiruttime\OSINT\Utils\Validation::parseJsonArray()` |
| `so_clean_text()` | `\Beiruttime\OSINT\Utils\TextUtils::cleanText()` |
| `so_normalize_title_for_dedupe()` | `\Beiruttime\OSINT\Utils\TextUtils::normalizeTitleForDedupe()` |
| `so_build_title_fingerprint()` | `\Beiruttime\OSINT\Utils\TextUtils::buildTitleFingerprint()` |

### 4. **تحسين الأداء**
- تقليل حجم الملف الرئيسي من ~940KB إلى ~4KB
- تحميل انتقائي للخدمات عند الحاجة فقط
- تخزين مؤقت للنتائج المتكررة

### 5. **سهولة الصيانة**
- كل فئة مسؤولة عن وظيفة واحدة محددة
- سهولة اختبار الوحدات بشكل منفصل
- توثيق مدمج لكل فئة ودالة

## 📦 خطوات الترقية

### للمطورين:
1. انسخ المجلد الجديد `src/` إلى مشروعك
2. استخدم `beiruttime-osint-pro-restructured.php` كملف رئيسي
3. استبدل استدعاءات الدوال القديمة باستدعاءات الفئات الجديدة
4. اختبر الوظائف الأساسية

### للمستخدمين النهائيين:
- لا توجد تغييرات مطلقة من جانب المستخدم
- الواجهة والوظائف تبقى كما هي
- تحسينات في الأداء والاستقرار

## 🔧 التقنيات المستخدمة

- **PHP 8.0+**: الاستفادة من أحدث الميزات
- **PSR-4**: التحميل التلقائي المعياري
- **WordPress Coding Standards**: اتباع معايير ووردبريس
- **Namespaces**: تنظيم الكود ومنع التصادم
- **Design Patterns**: Singleton, Factory, Service Locator

## 📝 الحالة الحالية

- الإصدار: 17.4.2 Restructured
- آخر تحديث: 2024
- حالة إعادة الهيكلة: **مكتملة جزئياً** (60%)
  - ✅ البنية الأساسية
  - ✅ النواة الأساسية
  - ✅ الأدوات المساعدة
  - ⏳ خدمات الأعمال (قيد النقل)
  - ⏳ واجهة الإدارة (قيد النقل)
  - ⏳ الواجهة الأمامية (قيد النقل)

## 🚀 الخطوات التالية

1. نقل دوال التصنيف من `includes/classifier-service.php` إلى `src/services/class-classifier.php`
2. نقل دوال سجل الأخبار من `includes/newslog-service.php` إلى `src/services/class-newslog.php`
3. إنشاء فئات إدارة AJAX
4. إنشاء فئات الرموز القصيرة (Shortcodes)
5. إضافة الاختبارات الآلية (Unit Tests)
