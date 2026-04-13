# OSINT Pro - البنية المعمارية المعيارية

## ملخص التطويرات الجديدة

تم إعادة هيكلة الإضافة بالكامل لتستخدم بنية معيارية حديثة توفر:

### ✅ 1. النظام المعياري (Modular Architecture)

**الفوائد:**
- فصل الوظائف إلى وحدات مستقلة
- سهولة الصيانة والتطوير
- إمكانية تفعيل/تعطيل الميزات دون التأثير على النظام
- تقليل التداخل بين الكود

**الملفات الأساسية:**
- `includes/class-modular-core.php` - النواة المركزية
- `includes/modules/class-module-interface.php` - واجهة الوحدات
- `includes/modules/class-base-module.php` - الفئة الأساسية للوحدات

### ✅ 2. نظام التخزين المؤقت المتقدم (Caching System)

**المميزات:**
- دعم تلقائي لـ Redis, Memcached, أو WordPress Transients
- اكتشاف أفضل Backend متاح تلقائيًا
- إدارة ذكية للكاش مع TTL مخصص
- تنظيف تلقائي للكاش المنتهي الصلاحية

**الاستخدام:**
```php
$cache = OSINT_Cache_Handler::get_instance();
$cache->set('key', $data, 3600);
$data = $cache->get('key');
```

**التكوين في `wp-config.php`:**
```php
// Redis
define('OSINT_REDIS_HOST', '127.0.0.1');
define('OSINT_REDIS_PORT', 6379);

// Memcached
define('OSINT_MEMCACHED_HOST', '127.0.0.1');
define('OSINT_MEMCACHED_PORT', 11211);
```

### ✅ 3. التحديث اللحظي (Real-time Updates)

**المميزات:**
- دعم WebSocket للاتصال ثنائي الاتجاه
- دعم Server-Sent Events (SSE) كحل بديل
- قنوات متعددة (alerts, map, analysis, general)
- إعادة اتصال تلقائية عند انقطاع الاتصال

**الاستخدام من الواجهة الأمامية:**
```javascript
const eventSource = new EventSource(osintRealtimeConfig.sseUrl);
eventSource.addEventListener('map', (e) => {
    const data = JSON.parse(e.data);
    // تحديث الخريطة
});
```

### ✅ 4. نظام الاختبارات الآلية (PHPUnit Testing)

**المميزات:**
- إعداد كامل لاختبارات WordPress
- اختبارات جاهزة لنظام الكاش
- مساعدات اختبار (Test Helpers)
- تكامل مع GitHub Actions (قادم)

**تشغيل الاختبارات:**
```bash
composer install
vendor/bin/phpunit
```

## هيكل الملفات الجديد

```
beiruttime-osint-pro/
├── includes/
│   ├── class-modular-core.php      # ⭐ النواة المركزية
│   ├── modules/                     # 📦 الوحدات النمطية
│   │   ├── class-module-interface.php
│   │   └── class-base-module.php
│   ├── handlers/                    # 🔧 معالجات البيانات
│   ├── services/                    # 🛠️ الخدمات
│   ├── cache/                       # ⚡ نظام الكاش
│   │   └── class-cache-handler.php
│   └── websocket/                   # 🔄 التحديث اللحظي
│       └── class-websocket-handler.php
├── tests/
│   ├── bootstrap.php                # إعداد الاختبارات
│   ├── Unit/                        # اختبارات الوحدة
│   │   └── CacheHandlerTest.php
│   └── helpers/                     # مساعدات الاختبار
│       └── class-test-helpers.php
├── assets/
│   └── js/modules/                  # وحدات JS
│       └── realtime.js (قادم)
├── composer.json                    # تبعيات PHP
├── phpunit.xml                      # إعداد PHPUnit
├── DEVELOPMENT.md                   # دليل المطورين
└── README_MODULAR.md                # هذا الملف
```

## خارطة الطريق القادمة

### المرحلة 1: الأساس (مكتملة ✅)
- [x] نظام معياري أساسي
- [x] نظام كاش متعدد الخيارات
- [x] نظام تحديث لحظي (SSE)
- [x] إعداد PHPUnit

### المرحلة 2: تحويل الوحدات الحالية (قادمة)
- [ ] نقل Dashboard Module إلى بنية معيارية
- [ ] نقل Map Module إلى بنية معيارية
- [ ] نقل Chart Module إلى بنية معيارية
- [ ] نقل Analysis Module إلى بنية معيارية

### المرحلة 3: ميزات متقدمة (قادمة)
- [ ] خادم WebSocket كامل مع Ratchet
- [ ] دعم GraphQL API
- [ ] نظام إشعارات متقدم
- [ ] لوحة تحكم للإضافات

### المرحلة 4: التحسين والأداء (قادمة)
- [ ] تحسين استعلامات قاعدة البيانات
- [ ] إضافة Object Cache متقدم
- [ ] دعم Queue System للعمليات الثقيلة
- [ ] تحليل أداء مدمج

## الترقية من الإصدار السابق

### خطوات الترقية:

1. **نسخ احتياطي:**
   ```bash
   cp -r wp-content/plugins/beiruttime-osint-pro \
       wp-content/plugins/beiruttime-osint-pro.backup
   ```

2. **تثبيت الملفات الجديدة:**
   ```bash
   git pull origin main
   ```

3. **تثبيت التبعيات:**
   ```bash
   cd wp-content/plugins/beiruttime-osint-pro
   composer install --no-dev
   ```

4. **إعداد الكاش (اختياري):**
   أضف إلى `wp-config.php`:
   ```php
   define('OSINT_REDIS_HOST', '127.0.0.1');
   ```

5. **اختبار النظام:**
   ```bash
   vendor/bin/phpunit
   ```

## المساهمة في التطوير

نرحب بالمساهمات! يرجى اتباع الخطوات التالية:

1. Fork المستودع
2. أنشئ فرع جديد: `git checkout -b feature/my-feature`
3. اكتب اختبارات للميزة الجديدة
4. تأكد من نجاح جميع الاختبارات: `composer test`
5. راجع الكود مع معايير WordPress: `composer lint`
6. أرسل Pull Request

## الدعم والمساعدة

للأسئلة والمشاكل:
- افتح Issue على GitHub
- راجع ملف `DEVELOPMENT.md` للتفاصيل التقنية
- تحقق من الاختبارات الموجودة لفهم كيفية عمل النظام

---

**الإصدار**: 2.0.0-modular  
**الحالة**: تطوير نشط  
**آخر تحديث**: 2024
