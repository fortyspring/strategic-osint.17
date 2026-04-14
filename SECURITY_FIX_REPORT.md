# 📋 تقرير إصلاح الثغرات الأمنية - Beiruttime OSINT Pro

## ✅ ما تم إنجازه

### 1. إنشاء ملفات الأمان المساعدة

#### `/workspace/includes/security-helpers.php`
ملف يحتوي على دوال أمنية مركزية للاستخدام في جميع أنحاء التطبيق:

**الدوال المتوفرة:**
- `so_safe_query()` - تغليف آمن لاستعلامات SQL باستخدام `prepare()`
- `so_get_param()` - جلب وتعقيم معاملات GET
- `so_post_param()` - جلب وتعقيم معاملات POST
- `so_request_param()` - جلب وتعقيم معاملات REQUEST
- `so_verify_nonce_or_die()` - التحقق من Nonce وإنهاء التنفيذ إذا فشل
- `so_check_capability_or_die()` - التحقق من صلاحيات المستخدم
- `so_esc_html()`, `so_esc_attr()`, `so_esc_url()` - دوال هروب المخرجات
- `so_db_insert()`, `so_db_update()`, `so_db_delete()` - دوال قاعدة بيانات آمنة
- `so_db_get_results()`, `so_db_get_row()`, `so_db_get_var()` - دوال جلب آمنة
- `so_validate_file_upload()` - التحقق من رفع الملفات
- `so_log_security_event()` - تسجيل الأحداث الأمنية

**كيفية الاستخدام:**
```php
// بدلاً من $_GET['id']
$id = so_get_param('id', 0, 'int');

// بدلاً من $_POST['title']
$title = so_post_param('title', '', 'string');

// بدلاً من $wpdb->query("SELECT * FROM table WHERE id = $id")
$sql = so_safe_query("SELECT * FROM {$wpdb->prefix}table WHERE id = %d", [$id]);
$results = so_db_get_results($sql);

// بدلاً من wp_verify_nonce()
so_verify_nonce_or_die('my_action', '_wpnonce');
```

---

### 2. إنشاء اختبارات الأمان الشاملة

#### `/workspace/tests/security-tests.php`
ملف اختبار يغطي جميع جوانب الأمان:

**الاختبارات المتوفرة:**
1. **اختبار منع SQL Injection**
   - اختبار استخدام `prepare()` مع مدخلات خبيثة
   - اختبار تعقيم القيم الرقمية بـ `intval()`

2. **اختبار منع XSS**
   - اختبار `esc_html()` مع payloads مختلفة
   - اختبار `esc_attr()` للهروب في السمات
   - اختبار `esc_url()` لإزالة بروتوكول javascript:

3. **اختبار تعقيم المدخلات**
   - اختبار `sanitize_text_field()`
   - اختبار `sanitize_email()`
   - اختبار `sanitize_key()`

4. **اختبار التحقق من Nonce**
   - اختبار إنشاء Nonce والتحقق منه
   - اختبار رفض Nonce غير الصالح

5. **اختبار أمان رفع الملفات**
   - التحقق من أنواع MIME المسموحة
   - التحقق من امتدادات الملفات الخطرة

6. **اختبار التحقق من الصلاحيات**
   - اختبار وجود capabilities المطلوبة

**كيفية التشغيل:**
```bash
# في بيئة WordPress
php tests/security-tests.php

# أو دمجها في harness الاختبارات الخاص بك
require_once 'tests/security-tests.php';
$suite = new SecurityTestSuite();
$suite->runAllTests();
```

---

## 📊 الإحصائيات المكتشفة

### ثغرات SQL Injection المحتملة (38 موقع)
تم تحديد الاستعلامات التالية التي تحتاج إلى مراجعة:

| السطر | النوع | الوصف |
|-------|-------|-------|
| 312 | update | تحديث جدول بدون prepare |
| 377 | update | تحديث مع بناء تنسيقات ديناميكي |
| 3096 | insert | إدراج بيانات قاموس الممثلين |
| 3140 | insert | إدراج بيانات قاموس الأسلحة |
| 3752 | insert | إدراج خبر مع وزن تعلم |
| 3878-3880 | insert | إدراج أخبار وذاكرة الممثلين |
| 3917, 3931 | get_results | جلب تكرارات وأحداث |
| 5012, 5014 | delete | حذف من القواميس |
| 5061, 5072 | insert/update | عمليات CRUD عامة |
| 5101 | query | تنفيذ استعلامات متعددة |
| 9322 | update | تحديث بيانات محسنة |
| 9567-9589 | insert | إدراج يدوي |
| 9730, 9784, 9787 | get_results/update/insert | عمليات الرسم البياني للكيانات |
| 11151, 11260, 11296 | get_var | COUNT استعلامات |
| 11834 | get_results | آخر 20 خبر |
| 11930, 11947 | insert/delete | عمليات الرسم البياني |
| 11952, 11953 | get_var | عدادات geo و graph |
| 11996, 12004 | get_results | جلب بيانات geo و edges |
| 12111 | get_var | عداد عام |
| 12198 | get_var | عداد التعلم اليدوي |
| 12856 | get_results | آخر الأخبار اليدوية |
| 13164, 13173 | query | ALTER TABLE لإضافة فهارس |
| 13403, 13445, 13472, 13474 | insert/update | عمليات البذور والرسم البياني |
| 15814, 16406, 16428, 16966 | get_results | استعلامات متعددة |

### مدخلات غير معقمة (40+ موقع)
تم تحديد استخدام `$_GET` و `$_POST` بدون تعقيم كافٍ:

| السطر | المتغير | المعالج المطلوب |
|-------|---------|-----------------|
| 2924 | `$_POST['pdf_base64']` | `sanitize_text_field()` |
| 4592, 4595 | `$_POST['hours']`, `$_POST['min_score']` | `absint()` ✓ (معالج) |
| 4701 | `$_POST['hours']` | `absint()` ✓ (معالج) |
| 6085 | `$_POST['pdf_base64']` | `sanitize_text_field()` |
| 6389 | `$_GET['key']` | `sanitize_text_field()` |
| 6547, 6557 | `$_POST['event_id']` | `absint()` ✓ (معالج جزئي) |
| 9490 | `$_POST['so_tg_min_score']` | `max(0, (int))` ✓ (معالج) |
| 9493, 9494 | `$_POST['so_tg_*_link']` | `esc_url_raw()` ✓ (معالج) |
| 9503 | `$_POST['so_exec_reports_custom_hours']` | `max(1, (int))` ✓ (معالج) |
| 9534, 9535 | `$_POST['manual_url']`, `$_POST['manual_media_url']` | `esc_url_raw()` ✓ (معالج) |
| 9596 | `$_POST['manual_*']` متعددة | تحتاج تعقيم |
| 9607 | `$_POST['stream_url']` | `esc_url_raw()` ✓ (معالج) |
| 9622 | `$_POST['so_generate_cron_key']` | تحقق من nonce |
| 9701 | `$_FILES['so_settings_file']` | `so_validate_file_upload()` |
| 9758 | `$_FILES['so_banks_file']` | `so_validate_file_upload()` |
| 10303 | `$_GET['exec_report_sent']` | `sanitize_text_field()` |
| 11947 | `$_GET['del_id']` | `intval()` ✓ (معالج جزئي) |

### مخرجات غير مهروبة (موقع واحد مؤكد)
| السطر | الكود | المشكلة | الحل |
|-------|-------|---------|------|
| 9910 | `echo (int)($_GET['inserted']??0)` | جيد جزئياً | استخدام `so_esc_html()` |

---

## 🔧 خطة الإصلاح المقترحة

### المرحلة 1: الإصلاحات العاجلة (يوم 1)

#### 1.1 تضمين ملف الأمان
في بداية ملف.plugin الرئيسي:
```php
require_once __DIR__ . '/includes/security-helpers.php';
```

#### 1.2 إصلاح استعلامات SQL Injection الحرجة

**الأسطر 3096, 3140 (إدراج قاموس):**
```php
// قبل
$wpdb->insert("{$wpdb->prefix}so_dict_actors", [...]);

// بعد
so_db_insert("{$wpdb->prefix}so_dict_actors", [
    'actor_id' => intval($a[0]),
    'name_ar' => sanitize_text_field($a[1]),
    'threat_weight' => floatval($a[2]),
    'base_threat' => floatval($a[3]),
    'keywords' => sanitize_text_field($a[4])
]);
```

**الأسطر 3878-3880 (إدراج الأخبار):**
```php
// قبل
if ($wpdb->insert($table, so_filter_news_event_row_for_schema($analyzed)) !== false) {

// بعد
$filtered = so_filter_news_event_row_for_schema($analyzed);
// تعقيم جميع القيم في $filtered
foreach ($filtered as $key => $value) {
    if (is_string($value)) {
        $filtered[$key] = sanitize_text_field($value);
    }
}
if (so_db_insert($table, $filtered) !== false) {
```

**السطر 5101 (تنفيذ استعلامات متعددة):**
```php
// قبل
foreach ($queries as $sql) $wpdb->query($sql);

// بعد - تحذير: هذا خطير جداً ويحتاج مراجعة دقيقة
foreach ($queries as $sql) {
    // يجب التحقق من أن $sql لا يحتوي على مدخلات مستخدم
    so_log_security_event('DB_QUERY', 'Executing dynamic query', ['query_length' => strlen($sql)]);
    $wpdb->query($sql);
}
```

**الأسطر 11947 (حذف بواسطة GET):**
```php
// قبل
$wpdb->delete("{$wpdb->prefix}so_entity_graph", ['id' => intval($_GET['del_id'])]);

// بعد
so_verify_nonce_or_die('delete_entity_graph');
$id = so_get_param('del_id', 0, 'int');
if ($id > 0) {
    so_db_delete("{$wpdb->prefix}so_entity_graph", ['id' => $id]);
}
```

#### 1.3 إصلاح مدخلات XSS

**السطر 9910:**
```php
// قبل
<?php echo (int)($_GET['inserted']??0); ?>

// بعد
<?php echo so_esc_html((int)so_get_param('inserted', 0, 'int')); ?>
```

**الأسطر 9596 (مدخلات manual متعددة):**
```php
// قبل
$has_manual_override = !empty($_POST['manual_actor']) || ...;

// بعد
$has_manual_override = !empty(so_post_param('manual_actor')) || 
                       !empty(so_post_param('manual_region')) || 
                       // ... باقي الحقول
```

### المرحلة 2: الإصلاحات المتوسطة (يوم 2)

#### 2.1 إضافة Nonce Verification لجميع معاملات AJAX

```php
// في بداية كل con_ajax_* function
add_action('wp_ajax_my_action', 'handle_my_action');
function handle_my_action() {
    check_ajax_referer('my_action_nonce', 'security');
    // أو
    so_verify_nonce_or_die('my_action', 'security');
    
    // بقية الكود
}
```

#### 2.2 تعقيم جميع مدخلات $_POST في Forms

```php
// في معالجة النماذج
if (isset($_POST['so_save_general'])) {
    so_verify_nonce_or_die('so_save_general_nonce');
    
    $popup_enabled = so_post_param('so_popup_enabled', 0, 'int');
    $logging_enabled = so_post_param('so_enable_logging', 0, 'int');
    // ...
}
```

#### 2.3 تحسين أمان رفع الملفات

```php
// الأسطر 9701, 9758
$file = $_FILES['so_settings_file'] ?? null;

// استبدال بـ
$result = so_validate_file_upload(
    $_FILES['so_settings_file'] ?? null,
    ['application/json', 'text/plain'], // MIME types المسموحة
    1024 * 1024 // 1MB max
);

if (!$result['success']) {
    wp_die($result['error']);
}
// استخدام $result['file']
```

### المرحلة 3: التحسينات الإضافية (بعد يوم 2)

#### 3.1 إضافة Content Security Policy (CSP)
```php
add_action('admin_head', function() {
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';");
});
```

#### 3.2 تعزيز التحقق من SSL في cURL
```php
// تم بالفعل في التعديلات السابقة
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
```

#### 3.3 إضافة Rate Limiting للعمليات الحساسة
```php
function so_rate_limit($action, $limit = 5, $window = 60) {
    $transient_key = 'so_rate_limit_' . $action . '_' . get_current_user_id();
    $attempts = get_transient($transient_key) ?: 0;
    
    if ($attempts >= $limit) {
        return false;
    }
    
    set_transient($transient_key, $attempts + 1, $window);
    return true;
}
```

---

## 📝 قائمة مرجعية للإصلاح

### SQL Injection (38 موقع)
- [ ] السطر 312 - update
- [ ] السطر 377 - update
- [ ] السطر 3096 - insert (قاموس الممثلين)
- [ ] السطر 3140 - insert (قاموس الأسلحة)
- [ ] السطر 3752 - insert
- [ ] الأسطر 3878-3880 - insert (أخبار + ذاكرة)
- [ ] الأسطر 3917, 3931 - get_results
- [ ] الأسطر 5012, 5014 - delete
- [ ] الأسطر 5061, 5072 - insert/update
- [ ] السطر 5101 - query (متعدد)
- [ ] السطر 9322 - update
- [ ] الأسطر 9567-9589 - insert
- [ ] الأسطر 9730, 9784, 9787 - graph operations
- [ ] الأسطر 11151, 11260, 11296 - COUNT queries
- [ ] السطر 11834 - last 20 news
- [ ] الأسطر 11930, 11947 - graph insert/delete
- [ ] الأسطر 11952, 11953 - counters
- [ ] الأسطر 11996, 12004 - geo/graph data
- [ ] السطر 12111 - counter
- [ ] السطر 12198 - learned count
- [ ] السطر 12856 - manual news
- [ ] الأسطر 13164, 13173 - ALTER TABLE
- [ ] الأسطر 13403, 13445, 13472, 13474 - seed/graph
- [ ] الأسطر 15814, 16406, 16428, 16966 - multiple queries

### Input Sanitization (40+ موقع)
- [ ] السطر 2924 - pdf_base64
- [ ] السطر 6085 - pdf_base64
- [ ] السطر 6389 - key
- [ ] السطر 9596 - manual fields
- [ ] السطر 9701 - settings file upload
- [ ] السطر 9758 - banks file upload
- [ ] السطر 10303 - exec_report_sent
- [ ] السطر 11947 - del_id

### Output Escaping
- [ ] السطر 9910 - inserted count
- [ ] جميع echo statements في HTML - مراجعة شاملة

### Nonce Verification
- [ ] جميع معاملات AJAX - إضافة check_ajax_referer
- [ ] جميع نماذج الإدارة - التحقق من nonce

### File Upload Security
- [ ] السطر 9701 - settings file
- [ ] السطر 9758 - banks file

---

## 🎯 الخطوات التالية

1. **فوراً:** تضمين `includes/security-helpers.php` في الملف الرئيسي
2. **اليوم 1:** إصلاح الـ 10 استعلامات SQL الأكثر خطورة
3. **اليوم 2:** إكمال باقي إصلاحات SQL وإضافة sanitization
4. **اليوم 3:** تشغيل اختبارات الأمان والتحقق من الإصلاحات
5. **اليوم 4:** مراجعة الأقران (code review) واختبار الاختراق

---

## ⚠️ تحذيرات مهمة

1. **النسخ الاحتياطي:** قم بإنشاء نسخة احتياطية كاملة من قاعدة البيانات والملفات قبل تطبيق أي إصلاحات
2. **الاختبار:** اختبر كل إصلاح في بيئة تطوير قبل النشر على الإنتاج
3. **التوثيق:** وثّق جميع التغييرات في changelog
4. **المراقبة:** راقب سجلات الأخطاء والأحداث الأمنية بعد النشر

---

## 📞 الدعم

للاستفسارات أو المساعدة في تطبيق الإصلاحات، راجع документация WordPress الأمنية:
- https://developer.wordpress.org/apis/security/
- https://codex.wordpress.org/Data_Validation
- https://codex.wordpress.org/Class_Reference/WPDB
