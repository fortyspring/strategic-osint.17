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

// تعريف الثابت إذا لم يكن معرفاً
if (!defined('BEIRUTTIME_OSINT_PRO_PLUGIN_DIR')) {
    define('BEIRUTTIME_OSINT_PRO_PLUGIN_DIR', plugin_dir_path(dirname(__DIR__, 2)));
}

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
                        <td><?php echo esc_html__('إعادة التحليل الكامل', 'beiruttime-osint'); ?></td>
                        <td><?php echo esc_html__('إعادة تصنيف جميع الأخبار باستخدام خوارزميات الذكاء الاصطناعي المحدثة', 'beiruttime-osint'); ?></td>
                        <td>
                            <button id="reanalyze-all-btn" class="button button-primary">
                                <?php echo esc_html__('🚀 بدء إعادة التحليل (AJAX)', 'beiruttime-osint'); ?>
                            </button>
                            <span id="reanalyze-status" style="margin-left: 10px;"></span>
                        </td>
                    </tr>
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
            
            <div id="reanalyze-progress" style="margin-top: 20px; display: none;">
                <progress id="reanalyze-progress-bar" value="0" max="100"></progress>
                <span id="reanalyze-progress-text">0%</span>
            </div>
            
            <div id="reanalyze-log" style="margin-top: 20px; background: #f0f0f1; padding: 15px; border-radius: 4px; max-height: 400px; overflow-y: auto; display: none;">
                <strong><?php echo esc_html__('سجل العمليات:', 'beiruttime-osint'); ?></strong>
                <div id="log-content"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#reanalyze-all-btn').on('click', function() {
                if (!confirm('<?php echo esc_js(__('هل أنت متأكد من بدء إعادة التحليل الكامل؟ قد تستغرق هذه العملية وقتًا طويلاً.', 'beiruttime-osint')); ?>')) {
                    return;
                }
                
                var $btn = $(this);
                var $status = $('#reanalyze-status');
                var $progress = $('#reanalyze-progress');
                var $progressBar = $('#reanalyze-progress-bar');
                var $progressText = $('#reanalyze-progress-text');
                var $log = $('#reanalyze-log');
                var $logContent = $('#log-content');
                
                $btn.prop('disabled', true);
                $progress.show();
                $log.show();
                $logContent.html('');
                
                function addLog(message) {
                    $logContent.append('<div>' + message + '</div>');
                    $log[0].scrollTop = $log[0].scrollHeight;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'so_reanalyze_all',
                        nonce: '<?php echo wp_create_nonce('so_reanalyze_action'); ?>'
                    },
                    xhrFields: {
                        onprogress: function(e) {
                            if (e.lengthComputable) {
                                var percent = Math.round((e.loaded / e.total) * 100);
                                $progressBar.val(percent);
                                $progressText.text(percent + '%');
                            }
                        }
                    },
                    success: function(response) {
                        if (response.success) {
                            addLog('<strong style="color: green;">✓ ' + response.data.message + '</strong>');
                            $status.html('<span style="color: green;">' + response.data.message + '</span>');
                        } else {
                            addLog('<strong style="color: red;">✗ ' + response.data + '</strong>');
                            $status.html('<span style="color: red;">' + response.data + '</span>');
                        }
                        $btn.prop('disabled', false);
                    },
                    error: function(xhr, status, error) {
                        addLog('<strong style="color: red;">✗ خطأ: ' + error + '</strong>');
                        $status.html('<span style="color: red;">خطأ في الاتصال</span>');
                        $btn.prop('disabled', false);
                    }
                });
                
                addLog('بدء عملية إعادة التحليل...');
            });
            
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
     * تحميل أصول الإدارة
     */
    public function enqueue_admin_assets($hook) {
        // تحميل assets فقط في صفحات الإضافة
        if (strpos($hook, 'beiruttime-osint') === false && strpos($hook, 'strategic-osint') === false) {
            return;
        }
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_media();
    }
}
