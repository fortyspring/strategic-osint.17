<?php
/**
 * فئة قائمة الإدارة
 * 
 * مسؤولة عن إضافة قوائم الإضافة إلى لوحة تحكم ووردبريس
 * 
 * @package Beiruttime\OSINT\Admin
 */

namespace Beiruttime\OSINT\Admin;

use Beiruttime\OSINT\Traits\Singleton;

/**
 * فئة AdminMenu
 */
class AdminMenu {
    
    use Singleton;
    
    /**
     * تهيئة القائمة
     */
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // AJAX handlers لإعادة التحليل والإحصائيات
        add_action('wp_ajax_so_ajax_reanalyze_batch', [$this, 'handle_full_reanalysis']);
        add_action('wp_ajax_so_ajax_reanalyze_reset', [$this, 'handle_reanalyze_reset']);
        add_action('wp_ajax_beiruttime_get_stats', [$this, 'handle_get_stats']);
    }
    
    /**
     * إضافة قائمة الإدارة
     */
    public function add_admin_menu() {
        // القائمة الرئيسية
        add_menu_page(
            __('Beiruttime OSINT Pro', 'beiruttime-osint'),
            __('OSINT Pro', 'beiruttime-osint'),
            'manage_options',
            'beiruttime-osint-pro',
            [$this, 'render_dashboard_page'],
            'dashicons-analytics',
            30
        );
        
        // صفحة لوحة التحكم
        add_submenu_page(
            'beiruttime-osint-pro',
            __('لوحة التحكم', 'beiruttime-osint'),
            __('لوحة التحكم', 'beiruttime-osint'),
            'manage_options',
            'beiruttime-osint-pro',
            [$this, 'render_dashboard_page']
        );
        
        // صفحة قاعدة البيانات الاستراتيجية
        add_submenu_page(
            'beiruttime-osint-pro',
            __('قاعدة البيانات الاستراتيجية', 'beiruttime-osint'),
            __('قاعدة البيانات الاستراتيجية', 'beiruttime-osint'),
            'manage_options',
            'strategic-osint-db',
            [$this, 'render_strategic_db_page']
        );
        
        // صفحة التصنيف والتحليل
        add_submenu_page(
            'beiruttime-osint-pro',
            __('التصنيف والتحليل', 'beiruttime-osint'),
            __('التصنيف والتحليل', 'beiruttime-osint'),
            'manage_options',
            'osint-classification',
            [$this, 'render_classification_page']
        );
        
        // صفحة التنبؤات
        add_submenu_page(
            'beiruttime-osint-pro',
            __('التنبؤات', 'beiruttime-osint'),
            __('التنبؤات', 'beiruttime-osint'),
            'manage_options',
            'osint-predictions',
            [$this, 'render_predictions_page']
        );
        
        // صفحة الإعدادات
        add_submenu_page(
            'beiruttime-osint-pro',
            __('الإعدادات', 'beiruttime-osint'),
            __('الإعدادات', 'beiruttime-osint'),
            'manage_options',
            'osint-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * عرض صفحة لوحة التحكم
     */
    public function render_dashboard_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('لوحة تحكم Beiruttime OSINT Pro', 'beiruttime-osint'); ?></h1>
            <div class="notice notice-info">
                <p><?php echo esc_html__('مرحبًا بك في منظومة التحليل الاستخباراتي المتقدمة.', 'beiruttime-osint'); ?></p>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2><?php echo esc_html__('إحصائيات سريعة', 'beiruttime-osint'); ?></h2>
                <p><?php echo esc_html__('جاري تحميل الإحصائيات...', 'beiruttime-osint'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * عرض صفحة قاعدة البيانات الاستراتيجية
     */
    public function render_strategic_db_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('قاعدة البيانات الاستراتيجية', 'beiruttime-osint'); ?></h1>
            
            <div class="notice notice-warning">
                <p><?php echo esc_html__('أدوات إعادة التحليل والتصنيف الشامل', 'beiruttime-osint'); ?></p>
            </div>
            
            <!-- نموذج إعادة التحليل المتوافق مع JavaScript -->
            <div class="card" style="max-width: 800px; margin-bottom: 20px;">
                <h2><?php echo esc_html__('إعادة التحليل الكامل', 'beiruttime-osint'); ?></h2>
                <p><?php echo esc_html__('إعادة تصنيف جميع الأخبار باستخدام خوارزميات الذكاء الاصطناعي المحدثة', 'beiruttime-osint'); ?></p>
                
                <div style="margin: 20px 0;">
                    <label for="so-re-batch"><?php echo esc_html__('حجم الدفعة:', 'beiruttime-osint'); ?></label>
                    <input type="number" id="so-re-batch" value="100" min="10" max="1000" step="50" style="width: 80px; margin: 0 10px;">
                    
                    <button id="so-re-run" class="button button-primary">
                        <?php echo esc_html__('🚀 بدء إعادة التحليل (AJAX)', 'beiruttime-osint'); ?>
                    </button>
                    <button id="so-re-reset" class="button button-secondary">
                        <?php echo esc_html__('تصفير المؤشر', 'beiruttime-osint'); ?>
                    </button>
                </div>
                
                <div id="so-re-msg" style="margin: 10px 0; padding: 10px; background: #f0f0f1; border-radius: 4px;"></div>
                
                <div style="margin: 15px 0;">
                    <div style="background: #ddd; height: 20px; border-radius: 3px; overflow: hidden;">
                        <div id="so-re-bar" style="width: 0%; height: 100%; background: #0073aa; transition: width 0.3s;"></div>
                    </div>
                    <div style="margin-top: 5px;">
                        <span id="so-re-percent">0%</span> - 
                        <?php echo esc_html__('تمت المعالجة:', 'beiruttime-osint'); ?> <span id="so-re-processed">0</span> / 
                        <span id="so-re-total">0</span> |
                        <?php echo esc_html__('المُحدّثة:', 'beiruttime-osint'); ?> <span id="so-re-updated">0</span> |
                        <?php echo esc_html__('الدفعة:', 'beiruttime-osint'); ?> <span id="so-re-batch-view">100</span> |
                        <?php echo esc_html__('الحالة:', 'beiruttime-osint'); ?> <span id="so-re-status"><?php echo esc_html__('متوقف', 'beiruttime-osint'); ?></span>
                    </div>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('الإجراء', 'beiruttime-osint'); ?></th>
                        <th><?php echo esc_html__('الوصف', 'beiruttime-osint'); ?></th>
                        <th><?php echo esc_html__('تنفيذ', 'beiruttime-osint'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo esc_html__('تحديث مصفوفات الجهات', 'beiruttime-osint'); ?></td>
                        <td><?php echo esc_html__('تحديث قائمة الجهات الفاعلة من الملفات المعيارية', 'beiruttime-osint'); ?></td>
                        <td>
                            <button id="update-actors-btn" class="button button-secondary">
                                <?php echo esc_html__('تحديث المصفوفات', 'beiruttime-osint'); ?>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#update-actors-btn').on('click', function() {
                alert('<?php echo esc_js(__('سيتم تحديث مصفوفات الجهات قريبًا', 'beiruttime-osint')); ?>');
            });
        });
        </script>
        <?php
    }
    
    /**
     * عرض صفحة التصنيف والتحليل
     */
    public function render_classification_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('التصنيف والتحليل', 'beiruttime-osint'); ?></h1>
            <p><?php echo esc_html__('أدوات التصنيف اليدوي والتحقق من النتائج الآلية.', 'beiruttime-osint'); ?></p>
        </div>
        <?php
    }
    
    /**
     * عرض صفحة التنبؤات
     */
    public function render_predictions_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'so_predictions';
        
        // التحقق من وجود الجدول
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists) {
            ?>
            <div class="wrap">
                <h1><?php echo esc_html__('التنبؤات', 'beiruttime-osint'); ?></h1>
                <div class="notice notice-error">
                    <p><?php echo esc_html__('جدول التنبؤات غير موجود. يرجى إعادة تفعيل الإضافة.', 'beiruttime-osint'); ?></p>
                </div>
            </div>
            <?php
            return;
        }
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('التنبؤات', 'beiruttime-osint'); ?></h1>
            
            <div class="notice <?php echo $count > 0 ? 'notice-success' : 'notice-warning'; ?>">
                <p>
                    <?php 
                    if ($count > 0) {
                        printf(esc_html__('عدد التنبؤات المتاحة: %d', 'beiruttime-osint'), $count);
                    } else {
                        esc_html_e('لا توجد تنبؤات حتى الآن. جدول التنبؤات فارغ.', 'beiruttime-osint');
                    }
                    ?>
                </p>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('معرف التنبؤ', 'beiruttime-osint'); ?></th>
                        <th><?php echo esc_html__('العنوان', 'beiruttime-osint'); ?></th>
                        <th><?php echo esc_html__('التاريخ', 'beiruttime-osint'); ?></th>
                        <th><?php echo esc_html__('الحالة', 'beiruttime-osint'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($count > 0) {
                        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 20");
                        foreach ($results as $row) {
                            echo '<tr>';
                            echo '<td>' . esc_html($row->id ?? '-') . '</td>';
                            echo '<td>' . esc_html($row->title ?? 'بدون عنوان') . '</td>';
                            echo '<td>' . esc_html($row->created_at ?? '-') . '</td>';
                            echo '<td>' . esc_html($row->status ?? 'غير محدد') . '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="4">' . esc_html__('لا توجد بيانات لعرضها', 'beiruttime-osint') . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * عرض صفحة الإعدادات
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('إعدادات Beiruttime OSINT Pro', 'beiruttime-osint'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('beiruttime_osint_options');
                do_settings_sections('beiruttime_osint_options');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * معالجة تصفير المؤشر (AJAX)
     */
    public function handle_reanalyze_reset() {
        check_ajax_referer('so_reanalyze_action', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'غير مصرح']);
            return;
        }

        // تصفير أي مؤشر تخزين مؤقت
        delete_option('beiruttime_osint_reanalyze_offset');
        
        wp_send_json_success(['message' => 'تم تصفير المؤشر بنجاح']);
    }

    /**
     * معالجة إعادة التحليل الكامل (AJAX)
     */
    public function handle_full_reanalysis() {
        check_ajax_referer('so_reanalyze_action', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'غير مصرح']);
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'so_newslog';
        
        // التحقق من وجود الجدول
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists) {
            wp_send_json_error(['message' => 'جدول الأخبار غير موجود']);
            return;
        }

        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 100;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        
        // جلب دفعة من الأخبار
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY id ASC LIMIT %d OFFSET %d",
            $batch_size,
            $offset
        ));

        if (empty($rows)) {
            wp_send_json_success([
                'done' => true,
                'processed' => $offset,
                'total' => $total,
                'message' => 'اكتمل إعادة التحليل'
            ]);
            return;
        }

        // معالجة كل خبر (هنا يتم استدعاء دوال التصنيف)
        $processed = 0;
        if (class_exists('Beiruttime\\OSINT\\Services\\Classifier')) {
            $classifier = Classifier::getInstance();
            
            foreach ($rows as $row) {
                $text = $row->title . ' ' . $row->content;
                
                // تحديث التصنيف
                $actor = $classifier->extractNamedNonMilitaryActor($text);
                if ($actor) {
                    $wpdb->update(
                        $table_name,
                        ['actor' => $actor],
                        ['id' => $row->id]
                    );
                }
                $processed++;
            }
        }

        wp_send_json_success([
            'done' => false,
            'processed' => $offset + $processed,
            'total' => $total,
            'message' => sprintf('تمت معالجة %d من %d', $offset + $processed, $total)
        ]);
    }

    /**
     * معالجة جلب الإحصائيات (AJAX)
     */
    public function handle_get_stats() {
        check_ajax_referer('beiruttime-osint-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'غير مصرح']);
            return;
        }

        global $wpdb;
        $newslog_table = $wpdb->prefix . 'so_newslog';
        $predictions_table = $wpdb->prefix . 'so_predictions';
        
        $stats = [
            'news_count' => 0,
            'predictions_count' => 0,
            'actors' => []
        ];

        // عدد الأخبار
        if ($wpdb->get_var("SHOW TABLES LIKE '$newslog_table'") == $newslog_table) {
            $stats['news_count'] = $wpdb->get_var("SELECT COUNT(*) FROM $newslog_table");
            
            // إحصائيات الجهات الفاعلة
            $actors = $wpdb->get_results("SELECT actor, COUNT(*) as count FROM $newslog_table WHERE actor IS NOT NULL AND actor != '' GROUP BY actor ORDER BY count DESC LIMIT 10");
            foreach ($actors as $a) {
                $stats['actors'][] = ['name' => $a->actor, 'count' => $a->count];
            }
        }

        // عدد التنبؤات
        if ($wpdb->get_var("SHOW TABLES LIKE '$predictions_table'") == $predictions_table) {
            $stats['predictions_count'] = $wpdb->get_var("SELECT COUNT(*) FROM $predictions_table");
        }

        wp_send_json_success($stats);
    }

    /**
     * تحميل أصول الإدارة
     */
    public function enqueue_admin_assets($hook) {
        // تحميل assets فقط في صفحات الإضافة
        if (strpos($hook, 'beiruttime-osint') === false && strpos($hook, 'strategic-osint') === false && strpos($hook, 'osint-') === false) {
            return;
        }
        
        // تحميل ملفات CSS
        wp_enqueue_style(
            'beiruttime-osint-admin',
            BEIRUTTIME_OSINT_PRO_PLUGIN_URL . 'assets/css/admin-pages.css',
            ['wp-color-picker'],
            BEIRUTTIME_OSINT_PRO_VERSION
        );
        
        // تحميل ملفات JS
        wp_enqueue_script(
            'beiruttime-osint-db-admin',
            BEIRUTTIME_OSINT_PRO_PLUGIN_URL . 'assets/js/db-admin.js',
            ['jquery', 'wp-color-picker'],
            BEIRUTTIME_OSINT_PRO_VERSION,
            true
        );
        
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_media();
        
        // تمرير المتغيرات إلى JavaScript
        wp_localize_script('beiruttime-osint-db-admin', 'beiruttimeOsint', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('so_reanalyze_action'),
            'strings' => [
                'confirmReanalysis' => __('هل أنت متأكد من بدء إعادة التحليل الكامل؟ قد تستغرق هذه العملية وقتًا طويلاً.', 'beiruttime-osint'),
                'processing' => __('جاري المعالجة...', 'beiruttime-osint'),
                'completed' => __('اكتمل!', 'beiruttime-osint'),
                'error' => __('حدث خطأ', 'beiruttime-osint')
            ]
        ]);
    }
}
