# 🚀 دليل البدء السريع - إصلاح الثغرات الأمنية

## 📦 ما تم إنجازه حتى الآن

### الملفات الجاهزة للاستخدام:

| الملف | الوصف | الحالة |
|-------|-------|--------|
| `includes/security-helpers.php` | مكتبة دوال الأمان (15+ دالة) | ✅ جاهز |
| `tests/security-tests.php` | اختبارات أمنية شاملة | ✅ جاهز |
| `scripts/fix-sql-injection.php` | سكربت فحص SQL Injection الآلي | ✅ جاهز |
| `SECURITY_FIX_REPORT.md` | تقرير مفصل للإصلاحات المطلوبة | ✅ جاهز |

---

## 🔧 خطوات الإصلاح (خطة 3 أيام)

### اليوم الأول: إصلاح ثغرات SQL Injection الحرجة

#### 1. تضمين مكتبة الأمان
أضف هذا السطر في بداية ملف `beiruttime-osint-pro.php`:

```php
require_once __DIR__ . '/includes/security-helpers.php';
```

#### 2. تشغيل سكربت الفحص
```bash
php scripts/fix-sql-injection.php beiruttime-osint-pro.php
```

#### 3. إصلاح الاستعلامات الـ 10 الأولى (الأهم)

**❌ قبل (خطر):**
```php
$wpdb->query("DELETE FROM {$wpdb->prefix}so_news_events WHERE id = $_GET['id']");
```

**✅ بعد (آمن):**
```php
$wpdb->prepare("DELETE FROM {$wpdb->prefix}so_news_events WHERE id = %d", intval($_GET['id']));
```

**أو باستخدام الدالة المساعدة:**
```php
so_safe_query("DELETE FROM {$wpdb->prefix}so_news_events WHERE id = %d", [intval($_GET['id'])]);
```

#### 4. الأسطر التي تحتاج إصلاح عاجل (من التقرير):
- سطر 3096: استعلام DELETE بدون prepare
- سطر 3140: استعلام UPDATE بدون prepare  
- سطر 3878-3880: استعلامات SELECT مع $_GET
- سطر 5101: استعلام INSERT مع $_POST
- سطر 11947: استعلام مع $_REQUEST

---

### اليوم الثاني: تعقيم المدخلات والمخرجات

#### 1. تعقيم جميع مدخلات $_GET و $_POST

**❌ قبل:**
```php
$search = $_GET['search'];
$email = $_POST['email'];
```

**✅ بعد:**
```php
$search = so_get_param('search', '', 'sanitize_text_field');
$email = so_post_param('email', '', 'sanitize_email');
```

#### 2. حماية مخرجات HTML من XSS

**❌ قبل:**
```php
echo "<div>$title</div>";
```

**✅ بعد:**
```php
echo "<div>" . esc_html($title) . "</div>";
```

#### 3. التحقق من Nonce في جميع معاملات AJAX

**❌ قبل:**
```php
add_action('wp_ajax_so_save_settings', 'so_save_settings');
```

**✅ بعد:**
```php
add_action('wp_ajax_so_save_settings', 'so_save_settings');

function so_save_settings() {
    so_verify_nonce_or_die('so_save_settings_nonce', 'nonce');
    // باقي الكود...
}
```

---

### اليوم الثالث: الاختبار والتحقق

#### 1. تشغيل اختبارات الأمان
```bash
php tests/security-tests.php
```

#### 2. اختبار الاختراق اليدوي
- جرب حقن SQL في جميع الحقول
- جرب XSS في جميع المدخلات
- تحقق من أن Nonce مفعّل في جميع AJAX

#### 3. مراجعة السجلات الأمنية
```php
// عرض آخر الأحداث الأمنية المسجلة
$logs = so_get_security_logs(50);
print_r($logs);
```

---

## 📊 قائمة مرجعية سريعة

### ✅ SQL Injection (38 موقع)
- [ ] استخدام `$wpdb->prepare()` لجميع الاستعلامات
- [ ] استبدال المتغيرات المباشرة بـ `%d`, `%s`, `%f`
- [ ] استخدام `so_safe_query()` للاستعلامات المعقدة

### ✅ XSS Prevention (40+ موقع)
- [ ] تعقيم جميع المدخلات بـ `sanitize_*`
- [ ] هروب جميع المخرجات بـ `esc_html()`, `esc_attr()`, `esc_url()`
- [ ] التحقق من Content-Type في رفع الملفات

### ✅ CSRF Protection
- [ ] إضافة Nonce لجميع النماذج
- [ ] التحقق من Nonce في جميع معاملات AJAX
- [ ] استخدام `wp_create_nonce()` و `wp_verify_nonce()`

### ✅ File Upload Security
- [ ] التحقق من MIME Type
- [ ] التحقق من الامتداد المسموح
- [ ] إعادة تسمية الملفات العشوائية
- [ ] تخزين خارج webroot إن أمكن

---

## 🎯 أوامر مفيدة

```bash
# تشغيل سكربت الفحص
php scripts/fix-sql-injection.php beiruttime-osint-pro.php

# تشغيل الاختبارات
php tests/security-tests.php

# عرض التقرير المفصل
cat SECURITY_FIX_REPORT.md

# عرض سجلات Git
git log --oneline

# إنشاء فرع للإصلاحات
git checkout -b security-fixes
```

---

## 📞 الدعم والمساعدة

إذا واجهت أي مشكلة:
1. راجع `SECURITY_FIX_REPORT.md` للتفاصيل الكاملة
2. افحص `includes/security-helpers.php` للأمثلة
3. شغل `tests/security-tests.php` للتحقق من الإصلاحات

---

## ⚠️ تحذيرات مهمة

- **لا تنشر الكود قبل إصلاح جميع ثغرات SQL Injection**
- **اختبر في بيئة تطوير قبل الإنتاج**
- **احتفظ بنسخة احتياطية قبل التعديل**
- **وثّق جميع التغييرات في Git**

---

**🔐 الأمان أولوية - لا تتهاون فيه!**
