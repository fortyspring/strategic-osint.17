# OSINT Pro - دليل التطوير

## البنية المعمارية الجديدة (Modular Architecture)

تم إعادة هيكلة الإضافة لتستخدم بنية معيارية قابلة للتطوير والصيانة.

### هيكل المجلدات

```
beiruttime-osint-pro/
├── includes/
│   ├── class-modular-core.php      # النواة المركزية
│   ├── modules/                     # الوحدات النمطية
│   │   ├── class-module-interface.php
│   │   └── class-base-module.php
│   ├── handlers/                    # معالجات البيانات
│   ├── services/                    # الخدمات (Telegram, AI, etc.)
│   ├── cache/                       # نظام التخزين المؤقت
│   └── websocket/                   # التحديث اللحظي
├── tests/
│   ├── bootstrap.php
│   ├── Unit/
│   └── helpers/
├── assets/
│   └── js/modules/                  # وحدات JavaScript
└── phpunit.xml
```

## 1. النظام المعياري (Modular System)

### إنشاء وحدة جديدة

```php
<?php
class OSINT_MyModule extends OSINT_Base_Module {
    
    public function get_id() {
        return 'my_module';
    }
    
    public function get_name() {
        return __('My Module', 'osint-pro');
    }
    
    public function get_version() {
        return '1.0.0';
    }
    
    public function init() {
        // Register hooks, scripts, etc.
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    public function render() {
        return '<div>Module Content</div>';
    }
    
    protected function ajax_custom_action($data) {
        // Handle AJAX request
        return array('result' => 'success');
    }
}
```

### تفعيل الوحدة

أضف اسم الفئة إلى مصفوفة `$module_classes` في `class-modular-core.php`.

## 2. نظام التخزين المؤقت (Caching)

### استخدام Redis

أضف إلى `wp-config.php`:

```php
define('OSINT_REDIS_HOST', '127.0.0.1');
define('OSINT_REDIS_PORT', 6379);
define('OSINT_REDIS_PASSWORD', 'your_password'); // اختياري
define('OSINT_REDIS_DB', 0);
```

### استخدام Memcached

```php
define('OSINT_MEMCACHED_HOST', '127.0.0.1');
define('OSINT_MEMCACHED_PORT', 11211);
```

### استخدام الكاش في الكود

```php
$cache = OSINT_Cache_Handler::get_instance();

// تعيين قيمة
$cache->set('my_key', $data, 3600);

// جلب قيمة
$data = $cache->get('my_key');

// حذف قيمة
$cache->delete('my_key');
```

## 3. التحديث اللحظي (Real-time Updates)

### WebSocket Configuration

أضف إلى `wp-config.php`:

```php
define('OSINT_WS_HOST', get_site_url());
define('OSINT_WS_PORT', 8080);
```

### تشغيل خادم WebSocket

```bash
# تثبيت Ratchet عبر Composer
composer require cboden/ratchet

# تشغيل الخادم
php bin/websocket-server.php
```

### الاشتراك في قناة من الواجهة الأمامية

```javascript
// الاتصال بـ SSE
const eventSource = new EventSource(osintRealtimeConfig.sseUrl);

eventSource.addEventListener('map', function(e) {
    const data = JSON.parse(e.data);
    // تحديث الخريطة
});

eventSource.addEventListener('alerts', function(e) {
    const data = JSON.parse(e.data);
    // عرض التنبيهات
});
```

## 4. الاختبارات الآلية (PHPUnit)

### تشغيل الاختبارات

```bash
# تثبيت WordPress test suite
bin/install-wp-tests.sh wordpress_test root '' localhost latest

# تشغيل جميع الاختبارات
vendor/bin/phpunit

# تشغيل مجموعة محددة
vendor/bin/phpunit --group cache

# تشغيل اختبار محدد
vendor/bin/phpunit --filter test_set_and_get
```

### كتابة اختبار جديد

```php
class MyModuleTest extends WP_UnitTestCase {
    
    public function test_something() {
        $this->assertTrue(true);
    }
}
```

## 5. معايير التطوير

### تسمية الملفات

- الفئات: `class-{classname}.php`
- الواجهات: `interface-{interfacename}.php`
- الاختبارات: `{ClassName}Test.php`

### التوثيق

استخدم PHPDoc لجميع الفئات والدوال:

```php
/**
 * وصف الدالة
 * 
 * @since 2.0.0
 * @param type $param وصف المعلمة
 * @return type وصف القيمة المسترجعة
 */
public function my_function($param) {
    // ...
}
```

### الأمان

- تحقق دائمًا من الصلاحيات: `current_user_can()`
- استخدم Nonce للتحقق من الطلبات
- نظف جميع المدخلات: `sanitize_text_field()`, `intval()`, etc.
- استخدم `esc_html()`, `esc_url()` للمخرجات

## 6. الأداء

### أفضل الممارسات

1. استخدم الكاش للاستعلامات الثقيلة
2. قلل استعلامات قاعدة البيانات
3. استخدم `WP_Query` بدلاً من SQL المباشر عندما ممكن
4. حمّل السكريبتات فقط عند الحاجة

### مراقبة الأداء

```php
// تفعيل وضع التصحيح
define('OSINT_DEBUG', true);
define('SAVEQUERIES', true);

// عرض إحصائيات الكاش
$stats = OSINT_Cache_Handler::get_instance()->get_stats();
print_r($stats);
```

## 7. المساهمة في المشروع

1. Fork المستودع
2. أنشئ فرع جديد للميزة: `git checkout -b feature/my-feature`
3. اكتب اختبارات للميزة الجديدة
4. تأكد من نجاح جميع الاختبارات
5. أرسل Pull Request

---

**الإصدار**: 2.0.0  
**آخر تحديث**: 2024
