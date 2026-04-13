# Beiruttime OSINT Pro - تحديثات الحرب المركبة v2.0

## 📋 ملخص التحديثات

تم إضافة ثلاثة محركات تحليلية متقدمة لتحويل الإضافة من مجرد أداة جمع أخبار إلى **منصة استخباراتية متكاملة** قادرة على فهم وتحليل الصراع الحديث بوصفه صراعاً مركباً متعدد الأدوات.

---

## 🎯 الملفات المضافة

### 1. محرك تحليل الحرب المركبة (`class-hybrid-warfare.php`)

**الوظيفة:** تحليل الأحداث ضمن طبقات الحرب المركبة التسع

#### الطبقات المدعومة:
| الرمز | الطبقة | الوزن |
|-------|--------|-------|
| L1 | العسكرية | 1.0 |
| L2 | الأمنية | 0.9 |
| L3 | السيبرانية | 0.95 |
| L4 | السياسية | 0.85 |
| L5 | الاقتصادية | 0.8 |
| L6 | الاجتماعية | 0.75 |
| L7 | الإعلامية والنفسية | 0.8 |
| L8 | الطاقة | 0.9 |
| L9 | الجيوستراتيجية | 0.95 |

#### الميزات الرئيسية:
- ✅ تحليل كل حدث ضمن الطبقات التسع
- ✅ حساب درجات المطابقة لكل طبقة
- ✅ كشف التوليفات بين الطبقات (مثل: عسكري + طاقة = استهداف بنية تحتية)
- ✅ تحديد الطبقة الأساسية المهيمنة
- ✅ تقييم درجة "التركيب المركب" للحدث

#### مثال على الاستخدام:
```php
use Beiruttime\OSINT\Services\HybridWarfareEngine;

$engine = HybridWarfareEngine::get_instance();
$analysis = $engine->analyzeLayers([
    'title' => 'غارة جوية تستهدف منشأة نفط',
    'description' => 'قصف جوي لمصفاة نفط استراتيجية'
]);

// النتيجة:
// - military: 0.85
// - energy: 0.92
// - is_hybrid: true
// - composite_score: 0.88
// - layer_combinations: ['military_energy' => 'استهداف البنية التحتية الحيوية']
```

---

### 2. نظام التحقق المتقدم (`class-verification.php`)

**الوظيفة:** التحقق من صحة الأحداث وحساب درجات الثقة

#### مستويات التحقق:
| المستوى | الوصف | الحد الأدنى للثقة |
|---------|-------|-------------------|
| ⚪ unconfirmed | غير مؤكد | 0 |
| ⚠️ conflicting_reports | تقارير متضاربة | 20 |
| 🔵 source_verified | تم التحقق من المصدر | 40 |
| 📍 location_verified | تم تحديد الموقع | 60 |
| ⏰ time_verified | تم تحديد الوقت | 50 |
| ✅ multi_source_verified | مؤكد من عدة مصادر | 75 |
| ✔️ officially_confirmed | مؤكد رسمياً | 90 |

#### عوامل التحقق:
1. **الأدلة البصرية** (25 نقطة): صور، فيديوهات، أقمار صناعية
2. **التحقق الجغرافي** (20 نقطة): إحداثيات، مواقع مؤكدة
3. **التحقق الزمني** (15 نقطة): توقيت دقيق للحدث والنشر
4. **تعدد المصادر** (25 نقطة): 3 مصادر أو أكثر
5. **المطابقة المرجعية** (15 نقطة): توافق مع أحداث سابقة
6. **خصم التناقضات**: -8 إلى -15 نقطة حسب الخطورة

#### مثال على الاستخدام:
```php
use Beiruttime\OSINT\Services\VerificationSystem;

$verifier = VerificationSystem::get_instance();
$result = $verifier->verifyEvent([
    'title' => 'انفجار في بيروت',
    'image_url' => 'https://...',
    'latitude' => 33.8938,
    'longitude' => 35.5018,
    'sources' => ['Reuters', 'AP', 'AFP'],
    'publish_time' => '2024-01-15 14:30:00'
]);

// النتيجة:
// - status: 'multi_source_verified'
// - confidence_score: 85
// - has_visual_evidence: true
// - sources_count: 3
```

---

### 3. نظام الإنذار المبكر (`class-early-warning.php`)

**الوظيفة:** كشف مؤشرات التصعيد وإصدار تنبيهات مبكرة

#### مستويات الإنذار:
| المستوى | اللون | الوصف |
|---------|-------|-------|
| green | 🟢 | وضع طبيعي |
| yellow | 🟡 | يقظة متزايدة |
| orange | 🟠 | تهديد محتمل |
| red | 🔴 | تهديد وشيك |
| critical | ⚫ | أزمة فعالة |

#### مؤشرات الإنذار المبكر (10 مؤشرات):
1. تحشيد عسكري (weight: 1.0)
2. خطاب تصعيدي (weight: 0.8)
3. انهيار دبلوماسي (weight: 0.9)
4. ضغط اقتصادي (weight: 0.7)
5. نشاط سيبراني مشبوه (weight: 0.85)
6. حملة إعلامية ممهدة (weight: 0.6)
7. أوامر إخلاء (weight: 0.95)
8. تفعيل دفاعات جوية (weight: 0.9)
9. تحركات بحرية (weight: 0.85)
10. تنبيه استخباراتي (weight: 0.95)

#### السيناريوهات التلقائية:
- 🎯 تصعيد عسكري وشيك (24-72 ساعة)
- 🎯 ضربة استباقية محتملة (12-48 ساعة)
- 🎯 هجوم سيبراني واسع النطاق (24-96 ساعة)
- 🎯 أزمة دبلوماسية حادة (48-168 ساعة)
- 🎯 حصار اقتصادي/بحري (72-168 ساعة)

#### مثال على الاستخدام:
```php
use Beiruttime\OSINT\Services\EarlyWarningSystem;

$ews = EarlyWarningSystem::get_instance();
$warning = $ews->analyzeEventsForWarnings($events);

// النتيجة:
// - alert_level: 'orange'
// - alert_level_ar: 'برتقالي - تهديد محتمل'
// - active_indicators_count: 4
// - scenarios: [
//     ['title' => 'تصعيد عسكري وشيك', 'probability' => 50]
//   ]
// - recommendations: [...]
```

---

## 🔧 التكامل مع النظام الحالي

### تحديث قاعدة البيانات المقترح

إضافة الحقول التالية لجدول الأحداث:

```sql
-- حقول الحرب المركبة
ALTER TABLE so_news_events ADD COLUMN hybrid_layers JSON NULL;
ALTER TABLE so_news_events ADD COLUMN primary_layer VARCHAR(50) NULL;
ALTER TABLE so_news_events ADD COLUMN composite_score DECIMAL(3,2) DEFAULT 0;
ALTER TABLE so_news_events ADD COLUMN is_hybrid_event TINYINT(1) DEFAULT 0;

-- حقول التحقق
ALTER TABLE so_news_events ADD COLUMN verification_status VARCHAR(50) DEFAULT 'unconfirmed';
ALTER TABLE so_news_events ADD COLUMN confidence_score INT DEFAULT 0;
ALTER TABLE so_news_events ADD COLUMN sources_count INT DEFAULT 0;
ALTER TABLE so_news_events ADD COLUMN has_visual_evidence TINYINT(1) DEFAULT 0;
ALTER TABLE so_news_events ADD COLUMN has_geolocation TINYINT(1) DEFAULT 0;

-- حقول الإنذار
ALTER TABLE so_news_events ADD COLUMN warning_indicators JSON NULL;
ALTER TABLE so_news_events ADD COLUMN alert_level VARCHAR(20) DEFAULT 'green';
ALTER TABLE so_news_events ADD COLUMN threat_score DECIMAL(3,2) DEFAULT 0;
```

### دمج الخدمات في المعالج الرئيسي

```php
// في ملف المعالجة الرئيسي
use Beiruttime\OSINT\Services\HybridWarfareEngine;
use Beiruttime\OSINT\Services\VerificationSystem;
use Beiruttime\OSINT\Services\EarlyWarningSystem;

function process_new_event($event_data) {
    // 1. تحليل الحرب المركبة
    $hybrid_engine = HybridWarfareEngine::get_instance();
    $hybrid_analysis = $hybrid_engine->analyzeLayers($event_data);
    
    // 2. التحقق من الحدث
    $verifier = VerificationSystem::get_instance();
    $verification = $verifier->verifyEvent($event_data);
    
    // 3. تحديث بيانات الحدث
    $event_data = array_merge($event_data, [
        'hybrid_layers' => json_encode($hybrid_analysis['layers']),
        'primary_layer' => $hybrid_analysis['primary_layer'],
        'composite_score' => $hybrid_analysis['composite_score'],
        'is_hybrid_event' => $hybrid_analysis['is_hybrid'] ? 1 : 0,
        'verification_status' => $verification['status'],
        'confidence_score' => $verification['confidence_score'],
        'sources_count' => $verification['sources_count'],
        'has_visual_evidence' => $verification['has_visual_evidence'] ? 1 : 0,
        'has_geolocation' => $verification['has_geolocation'] ? 1 : 0,
    ]);
    
    // 4. حفظ الحدث
    save_event($event_data);
    
    // 5. تحليل الإنذار المبكر (على مجموعة الأحداث)
    $recent_events = get_recent_events(50);
    $ews = EarlyWarningSystem::get_instance();
    $warning = $ews->analyzeEventsForWarnings($recent_events);
    
    // 6. إصدار تنبيه إذا لزم الأمر
    if ($warning['alert_level'] !== 'green') {
        issue_alert($warning);
    }
}
```

---

## 📊 واجهة المستخدم المقترحة

### لوحة القيادة الجديدة

```
┌─────────────────────────────────────────────────────────────┐
│  🚨 مستوى الإنذار الحالي: [🟠 برتقالي - تهديد محتمل]       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📈 مؤشرات النشاط (آخر 24 ساعة)                             │
│  ┌──────────┬──────────┬──────────┬──────────┐            │
│  │ عسكرية   │ سيبرانية │ سياسية   │ اقتصادية │            │
│  │   45     │    12    │    28    │    15    │            │
│  └──────────┴──────────┴──────────┴──────────┘            │
│                                                             │
│  🔍 أحداث هجينة مكتشفة                                      │
│  • غارة على منشأة نفط [عسكري+طاقة] - خطورة: حرجة          │
│  • اختراق موقع حكومي [سيبراني+سياسي] - خطورة: عالية       │
│                                                             │
│  ✅ حالة التحقق                                             │
│  ⚪ غير مؤكد: 15  🔵 مصدر واحد: 25  ✅ متعدد: 10           │
│                                                             │
│  🎯 سيناريوهات محتملة                                       │
│  • تصعيد عسكري وشيك (احتمال: 70%)                          │
│  • هجوم سيبراني واسع (احتمال: 60%)                         │
└─────────────────────────────────────────────────────────────┘
```

---

## 🚀 خطوات التفعيل

### 1. نسخ الملفات
```bash
cp src/services/class-hybrid-warfare.php /wp-content/plugins/beiruttime-osint-pro/src/services/
cp src/services/class-verification.php /wp-content/plugins/beiruttime-osint-pro/src/services/
cp src/services/class-early-warning.php /wp-content/plugins/beiruttime-osint-pro/src/services/
```

### 2. تحديث قاعدة البيانات
تنفيذ أوامر SQL المذكورة أعلاه

### 3. تضمين الخدمات
إضافة أسطر `require_once` في الملف الرئيسي:
```php
require_once __DIR__ . '/src/services/class-hybrid-warfare.php';
require_once __DIR__ . '/src/services/class-verification.php';
require_once __DIR__ . '/src/services/class-early-warning.php';
```

### 4. تعديل معالجة الأحداث
تحديث دالة معالجة الأحداث لاستخدام الخدمات الجديدة

### 5. تحديث واجهة المستخدم
إضافة widgets جديدة لعرض:
- طبقات الحرب المركبة
- حالة التحقق
- مستوى الإنذار
- السيناريوهات المحتملة

---

## 📝 ملاحظات مهمة

### الأمان والخصوصية
- جميع عمليات التحليل تتم محلياً
- لا يتم إرسال بيانات إلى خوارج خارجية
- درجات الثقة تُحسب بناءً على معايير موضوعية

### الأداء
- استخدام Singleton Pattern لتقليل استهلاك الذاكرة
- تخزين نتائج التحليل في Cache لتسريع الوصول
- معالجة غير متزامنة للتحليلات الثقيلة

### القابلية للتوسع
- يمكن إضافة طبقات جديدة بسهولة
- يمكن تخصيص قواميس الكلمات المفتاحية
- يمكن تعديل أوزان المؤشرات حسب السياق

---

## 📞 الدعم والتطوير

للأسئلة أو طلبات التطوير الإضافي:
- Telegram: [@osint_lb](https://t.me/osint_lb)
- GitHub: [fortyspring/strategic-osint.17](https://github.com/fortyspring/strategic-osint.17)

---

**الإصدار:** 2.0.0  
**تاريخ التحديث:** أبريل 2024  
**الحالة:** ✅ جاهز للإنتاج
