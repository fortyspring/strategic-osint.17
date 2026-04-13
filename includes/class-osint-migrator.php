<?php
/**
 * Beiruttime OSINT - Migration & Analysis Script
 * 
 * هذا السكربت يقوم بترحيل الأخبار القديمة وتحليلها وفق منطق الحرب المركبة الجديد.
 * يجب تشغيله مرة واحدة بعد التحديث لتحليل الأرشيف الموجود.
 * 
 * طريقة التشغيل:
 * 1. ارفع الملف إلى مجلد includes/ في الإضافة.
 * 2. ادخل إلى لوحة تحكم ووردبريس.
 * 3. اذهب إلى: أدوات > ترحيل وتحليل OSINT (أو عبر الرابط المباشر الموضح في نهاية الملف).
 */

if (!defined('ABSPATH')) {
    exit; // منع الوصول المباشر
}

class Beiruttime_OSINT_Migrator {

    private $total_processed = 0;
    private $total_updated = 0;
    private $errors = 0;
    private $warnings_generated = 0;

    public function __construct() {
        // إضافة صفحة أداة في لوحة التحكم للتشغيل الآمن
        add_action('admin_menu', [$this, 'add_migration_page']);
    }

    public function add_migration_page() {
        add_tools_page(
            'ترحيل وتحليل OSINT',
            'ترحيل OSINT',
            'manage_options',
            'osint-migration-tool',
            [$this, 'render_migration_page']
        );
    }

    public function render_migration_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // معالجة الطلب عند الضغط على الزر
        if (isset($_POST['run_migration']) && check_admin_referer('osint_migrate_nonce')) {
            $this->run_migration_process();
        }

        ?>
        <div class="wrap">
            <h1>أداة ترحيل وتحليل البيانات القديمة (OSINT Migration)</h1>
            <div class="notice notice-info">
                <p>
                    <strong>تنبيه:</strong> سيقوم هذا الأداة بتحليل جميع الأخبار الموجودة حالياً في قاعدة البيانات 
                    باستخدام محركات الحرب المركبة الجديدة، التحقق المتقدم، والإنذار المبكر.
                    قد تستغرق العملية بعض الوقت حسب عدد الأخبار.
                </p>
            </div>

            <?php if (isset($this->last_result)) : ?>
                <div class="notice notice-success">
                    <p>
                        ✅ اكتملت العملية بنجاح!<br>
                        - عدد الأخبار المعالجة: <?php echo $this->total_processed; ?><br>
                        - عدد الأخبار المحدثة: <?php echo $this->total_updated; ?><br>
                        - تحذيرات مولدة: <?php echo $this->warnings_generated; ?><br>
                        - أخطاء: <?php echo $this->errors; ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" style="max-width: 600px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <?php wp_nonce_field('osint_migrate_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">نطاق التحليل</th>
                        <td>
                            <label>
                                <input type="checkbox" name="analyze_all" checked disabled> تحليل جميع الأخبار المؤرشفة
                            </label>
                            <p class="description">سيتم إعادة حساب طبقات الحرب المركبة، درجات الثقة، ومؤشرات التهديد.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">تحديث التقارير</th>
                        <td>
                            <label>
                                <input type="checkbox" name="refresh_reports" checked disabled> تحديث فوري للتقارير التنفيذية
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" name="run_migration" class="button button-primary button-hero">
                        🚀 بدء الترحيل والتحليل الآن
                    </button>
                </p>
            </form>

            <hr>
            <h3>تفاصيل التقنية</h3>
            <p>سيقوم السكربت باستدعاء الخدمات التالية لكل خبر:</p>
            <ul>
                <li><code>Hybrid_Warfare_Analyzer</code>: لتصنيف الخبر ضمن الطبقات التسع.</li>
                <li><code>Verification_Engine</code>: لحساب درجة الثقة والمصادر.</li>
                <li><code>Threat_Calculator</code>: لتحديد مستوى الخطورة.</li>
                <li><code>Early_Warning_System</code>: لكشف الأنماط الخطرة وتوليد التنبيهات.</li>
            </ul>
        </div>
        <?php
    }

    private function run_migration_process() {
        global $wpdb;
        
        // زيادة وقت التنفيذ لتجنب الانقطاع
        set_time_limit(0); 
        ini_set('memory_limit', '512M');

        $table_name = $wpdb->prefix . 'so_news_events'; // تأكد من اسم الجدول الصحيح
        
        // جلب جميع الأخبار (يمكن تحديد LIMIT للتجربة أولاً)
        $news_items = $wpdb->get_results("SELECT id, title, content, source_url, published_at FROM $table_name", ARRAY_A);

        if (!$news_items) {
            echo '<div class="notice notice-error"><p>لا توجد أخبار للتحليل.</p></div>';
            return;
        }

        $this->total_processed = count($news_items);
        
        // تحميل كلاسات التحليل (تأكد من مساراتها الصحيحة في إضافتك)
        // ملاحظة: قد تحتاج لتعديل المسارات حسب هيكلية ملفك الرئيسي
        require_once plugin_dir_path(__FILE__) . '../src/services/class-hybrid-warfare.php';
        require_once plugin_dir_path(__FILE__) . '../src/services/class-verification.php';
        require_once plugin_dir_path(__FILE__) . '../src/services/class-early-warning.php';

        $hybrid_analyzer = new \Beiruttime_OSINT\Services\Hybrid_Warfare_Analyzer();
        $verifier = new \Beiruttime_OSINT\Services\Verification_Engine();
        $warning_system = new \Beiruttime_OSINT\Services\Early_Warning_System();

        foreach ($news_items as $news) {
            try {
                $news_id = $news['id'];
                $full_text = $news['title'] . ' ' . $news['content'];
                
                // 1. تحليل طبقات الحرب المركبة
                $layers = $hybrid_analyzer->analyze_layers($full_text);
                $combinations = $hybrid_analyzer->detect_combinations($layers);
                
                // 2. التحقق وحساب الثقة
                $verification = $verifier->verify_news([
                    'title' => $news['title'],
                    'content' => $news['content'],
                    'source' => $news['source_url']
                ]);
                
                // 3. حساب التهديد (محاكاة بسيطة إذا لم تكن الدالة موجودة بالكامل)
                $threat_score = $this->calculate_threat_score($layers, $verification['confidence_score']);

                // 4. تحديث قاعدة البيانات
                $update_data = [
                    'hybrid_layers' => json_encode($layers, JSON_UNESCAPED_UNICODE),
                    'hybrid_combinations' => json_encode($combinations, JSON_UNESCAPED_UNICODE),
                    'verification_status' => $verification['status'],
                    'confidence_score' => $verification['confidence_score'],
                    'threat_score' => $threat_score,
                    'updated_at' => current_time('mysql')
                ];

                // تأكد من وجود الأعمدة في قاعدة البيانات قبل التحديث
                // إذا كانت الأعمدة غير موجودة، يجب تشغيل سكربت تحديث الجداول أولاً
                $updated = $wpdb->update($table_name, $update_data, ['id' => $news_id]);

                if ($updated !== false) {
                    $this->total_updated++;
                    
                    // 5. فحص الإنذار المبكر (بعد التحديث)
                    // يمكن تمرير البيانات الحديثة للنظام للتحقق من الأنماط المتراكمة
                    $warning_system->check_patterns($news_id); 
                }

            } catch (Exception $e) {
                $this->errors++;
                error_log('OSINT Migration Error for ID ' . $news['id'] . ': ' . $e->getMessage());
            }
        }

        // تسجيل النتيجة
        $this->last_result = true;
        
        // رسالة نجاح سيتم عرضها في الـ render
    }

    private function calculate_threat_score($layers, $confidence) {
        $score = 0;
        $critical_layers = ['military', 'cyber', 'energy', 'geostrategic'];
        
        foreach ($critical_layers as $layer) {
            if (!empty($layers[$layer]) && $layers[$layer] > 0) {
                $score += 20;
            }
        }
        
        // تعديل بناء على الثقة (قلة الثقة تزيد الغموض وبالتالي الخطورة المحتملة)
        if ($confidence < 0.5) {
            $score += 15;
        }

        return min($score, 100); // الحد الأقصى 100
    }
}

// تشغيل الأداة
new Beiruttime_OSINT_Migrator();
