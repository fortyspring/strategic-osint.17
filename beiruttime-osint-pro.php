<?php
/**
 * Beiruttime OSINT Pro - Main Plugin File
 * Version: 17.4.3
 * Description: نظام الرصد الاستخباراتي الموحد مع تحليل الحرب المركبة
 */

if (!defined('ABSPATH')) {
    exit;
}

// منع التحميل المكرر
if (defined('BEIRUTTIME_OSINT_PRO_VERSION')) {
    return;
}

define('BEIRUTTIME_OSINT_PRO_VERSION', '17.4.3');
define('BEIRUTTIME_OSINT_PRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BEIRUTTIME_OSINT_PRO_PLUGIN_URL', plugin_dir_url(__FILE__));

// ==========================================================================
// دوال مساعدة عامة (محمية من التكرار)
// ==========================================================================

if (!function_exists('sod_has_arabic_chars')) {
    function sod_has_arabic_chars(string $text): bool {
        return preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text) === 1;
    }
}

if (!function_exists('sod_fix_mojibake_text')) {
    function sod_fix_mojibake_text($value): string {
        if (!is_string($value)) return '';
        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}

if (!function_exists('sod_normalize_string_list')) {
    function sod_normalize_string_list(array $values): array {
        return array_values(array_filter(array_map('trim', $values)));
    }
}

if (!function_exists('sod_json_flags')) {
    function sod_json_flags(): int {
        return JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    }
}

// ==========================================================================
// محركات الذكاء الاستخباراتي للتهديدات (Threat Intelligence AI)
// ==========================================================================

if (!function_exists('so_ti_recent_events')) {
    function so_ti_recent_events($limit = 150) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT title, actor_v2 as actor, region, intel_type as type, score, event_timestamp as time
                 FROM {$wpdb->prefix}so_news_events
                 ORDER BY event_timestamp DESC
                 LIMIT %d",
                (int) $limit
            ),
            ARRAY_A
        );
    }
}

if (!function_exists('so_ti_temporal_pressure')) {
    function so_ti_temporal_pressure($events) {
        $now = time();
        $last_2h = 0;
        $last_6h = 0;

        foreach ((array) $events as $e) {
            $t = (int) ($e['time'] ?? $now);
            if (($now - $t) <= 7200) $last_2h++;
            if (($now - $t) <= 21600) $last_6h++;
        }

        $ratio = $last_6h > 0 ? ($last_2h / max(1, $last_6h)) : 0;

        return array(
            'last_2h' => $last_2h,
            'last_6h' => $last_6h,
            'ratio'   => $ratio,
        );
    }
}

if (!function_exists('so_ti_actor_pressure')) {
    function so_ti_actor_pressure($events) {
        $actors = array();
        foreach ((array) $events as $e) {
            $actor = trim((string) ($e['actor'] ?? 'غير محدد'));
            if ($actor === '') $actor = 'غير محدد';
            if (!isset($actors[$actor])) $actors[$actor] = array('count' => 0, 'score' => 0);
            $actors[$actor]['count']++;
            $actors[$actor]['score'] += (int) ($e['score'] ?? 0);
        }
        uasort($actors, function ($a, $b) {
            return ($b['score'] <=> $a['score']) ?: ($b['count'] <=> $a['count']);
        });
        return $actors;
    }
}

if (!function_exists('so_ti_region_pressure')) {
    function so_ti_region_pressure($events) {
        $regions = array();
        foreach ((array) $events as $e) {
            $region = trim((string) ($e['region'] ?? 'غير محدد'));
            if ($region === '') $region = 'غير محدد';
            if (!isset($regions[$region])) $regions[$region] = array('count' => 0, 'score' => 0);
            $regions[$region]['count']++;
            $regions[$region]['score'] += (int) ($e['score'] ?? 0);
        }
        uasort($regions, function ($a, $b) {
            return ($b['score'] <=> $a['score']) ?: ($b['count'] <=> $a['count']);
        });
        return $regions;
    }
}

if (!function_exists('so_ti_type_pressure')) {
    function so_ti_type_pressure($events) {
        $types = array();
        foreach ((array) $events as $e) {
            $type = trim((string) ($e['type'] ?? 'عام'));
            if ($type === '') $type = 'عام';
            if (!isset($types[$type])) $types[$type] = array('count' => 0, 'score' => 0);
            $types[$type]['count']++;
            $types[$type]['score'] += (int) ($e['score'] ?? 0);
        }
        uasort($types, function ($a, $b) {
            return ($b['score'] <=> $a['score']) ?: ($b['count'] <=> $a['count']);
        });
        return $types;
    }
}

if (!function_exists('so_threat_probability')) {
    function so_threat_probability($events) {
        if (empty($events)) return 0;
        $score_sum = 0; $military = 0; $high = 0; $unknown = 0;

        foreach ((array) $events as $e) {
            $score = (int) ($e['score'] ?? 0);
            $type  = trim((string) ($e['type'] ?? ''));
            $actor = trim((string) ($e['actor'] ?? ''));
            $score_sum += $score;
            if (in_array($type, array('عسكري/أمني', 'تكتيكي/ميداني', 'رصد/إنذار', 'أمني'), true)) $military++;
            if ($score >= 100) $high++;
            if (strpos($actor, 'غير') !== false || strpos($actor, 'فاعل سياقي') !== false) $unknown++;
        }

        $avg_score       = $score_sum / max(1, count($events));
        $military_ratio  = $military / max(1, count($events));
        $high_ratio      = $high / max(1, count($events));
        $unknown_penalty = $unknown / max(1, count($events));

        $probability = ($avg_score * 0.50) + ($military_ratio * 100 * 0.30) + ($high_ratio * 100 * 0.25) - ($unknown_penalty * 100 * 0.20);
        return max(0, min(100, (int) round($probability)));
    }
}

if (!function_exists('so_ti_scenarios')) {
    function so_ti_scenarios($events) {
        $clusters = array();
        foreach ((array) $events as $e) {
            $key = trim((string) ($e['actor'] ?? 'غير محدد')) . '|' . trim((string) ($e['region'] ?? 'غير محدد')) . '|' . trim((string) ($e['type'] ?? 'عام'));
            if (!isset($clusters[$key])) $clusters[$key] = array();
            $clusters[$key][] = $e;
        }

        $scenarios = array();
        foreach ($clusters as $key => $items) {
            $parts = array_pad(explode('|', $key), 3, 'غير محدد');
            $actor = $parts[0]; $region = $parts[1]; $type = $parts[2];
            $count = count($items);
            $score = 0;
            foreach ($items as $it) $score += (int) ($it['score'] ?? 0);

            if ($count >= 5 && $score >= 400 && in_array($type, array('عسكري/أمني', 'تكتيكي/ميداني', 'رصد/إنذار', 'أمني'), true)) {
                $scenarios[] = array(
                    'title'       => 'تصعيد ميداني محتمل',
                    'description' => "نشاط متكرر لـ {$actor} في {$region} بنمط {$type} يشير إلى احتمالية تصعيد خلال الساعات القادمة.",
                    'confidence'  => 'مرتفع',
                    'score'       => $score,
                );
            } elseif ($count >= 4 && $score >= 180 && $type === 'سياسي') {
                $scenarios[] = array(
                    'title'       => 'ضغط تفاوضي / سياسي',
                    'description' => "تكرار التصريحات والرسائل السياسية من {$actor} في {$region} يوحي بمرحلة ضغط تفاوضي موازية للميدان.",
                    'confidence'  => 'متوسط',
                    'score'       => $score,
                );
            }
        }
        usort($scenarios, function ($a, $b) { return ($b['score'] <=> $a['score']); });
        return array_slice($scenarios, 0, 5);
    }
}

if (!function_exists('so_predict_next_event')) {
    function so_predict_next_event($events) {
        if (empty($events)) return array('actor' => 'غير محدد', 'region' => 'غير محدد', 'time' => date('H'), 'confidence' => 'ضعيف');

        $timeline = array(); $actors = array(); $regions = array();
        foreach ((array) $events as $e) {
            $time = (int) ($e['time'] ?? time());
            $hour = date('H', $time);
            if (!isset($timeline[$hour])) $timeline[$hour] = 0;
            $timeline[$hour]++;

            $actor = trim((string) ($e['actor'] ?? 'غير محدد'));
            if (!isset($actors[$actor])) $actors[$actor] = 0;
            $actors[$actor] += (int) ($e['score'] ?? 0);

            $region = trim((string) ($e['region'] ?? 'غير محدد'));
            if (!isset($regions[$region])) $regions[$region] = 0;
            $regions[$region] += (int) ($e['score'] ?? 0);
        }

        arsort($timeline); arsort($actors); arsort($regions);
        $confidence = count($events) > 200 ? 'مرتفع' : (count($events) < 50 ? 'ضعيف' : 'متوسط');

        return array(
            'actor'      => !empty($actors) ? key($actors) : 'غير محدد',
            'region'     => !empty($regions) ? key($regions) : 'غير محدد',
            'time'       => !empty($timeline) ? key($timeline) : date('H'),
            'confidence' => $confidence,
        );
    }
}

if (!function_exists('so_early_warning_engine')) {
    function so_early_warning_engine($events) {
        $prediction  = so_predict_next_event($events);
        $probability = so_threat_probability($events);
        $temporal    = so_ti_temporal_pressure($events);

        $level = 'منخفض';
        if ($probability > 75) $level = 'حرج';
        elseif ($probability > 55) $level = 'مرتفع';
        elseif ($probability > 35) $level = 'متوسط';

        return array(
            'probability' => $probability,
            'level'       => $level,
            'prediction'  => $prediction,
            'temporal'    => $temporal,
            'scenarios'   => so_ti_scenarios($events),
        );
    }
}

if (!function_exists('so_dispatch_early_warning')) {
    function so_dispatch_early_warning($alert) {
        if (empty($alert) || !is_array($alert)) return;
        if (($alert['level'] ?? 'منخفض') !== 'حرج') return;

        $sig = md5(wp_json_encode(array(
            'level'       => $alert['level'] ?? '',
            'probability' => $alert['probability'] ?? 0,
            'actor'       => $alert['prediction']['actor'] ?? '',
            'region'      => $alert['prediction']['region'] ?? '',
            'time'        => $alert['prediction']['time'] ?? '',
        )));

        $last_sig  = (string) get_option('so_last_early_warning_sig', '');
        $last_time = (int) get_option('so_last_early_warning_time', 0);

        if ($last_sig === $sig && (time() - $last_time) < 1800) return;

        update_option('so_last_early_warning_sig', $sig, false);
        update_option('so_last_early_warning_time', time(), false);

        $msg  = "🚨 إنذار قبل الحدث\n";
        $msg .= "مستوى الخطر: " . ($alert['level'] ?? 'غير محدد') . "\n";
        $msg .= "احتمال التصعيد: " . (int) ($alert['probability'] ?? 0) . "%\n";
        $msg .= "الفاعل المتوقع: " . ($alert['prediction']['actor'] ?? 'غير محدد') . "\n";
        $msg .= "المنطقة المتوقعة: " . ($alert['prediction']['region'] ?? 'غير محدد') . "\n";
        $msg .= "الوقت المرجح: " . ($alert['prediction']['time'] ?? date('H')) . ":00\n";

        $scenarios = !empty($alert['scenarios']) && is_array($alert['scenarios']) ? $alert['scenarios'] : array();
        if (!empty($scenarios[0]['title'])) $msg .= "السيناريو الأبرز: " . $scenarios[0]['title'] . "\n";

        if (class_exists('SO_Instant_Alerts')) {
            $s = SO_Instant_Alerts::get_settings();
            if (!empty($s['telegram_enabled']) && !empty($s['bot_token']) && !empty($s['chat_id'])) {
                $url = 'https://api.telegram.org/bot' . rawurlencode($s['bot_token']) . '/sendMessage';
                wp_remote_post($url, array(
                    'timeout' => 12,
                    'body'    => array('chat_id' => $s['chat_id'], 'text' => $msg),
                ));
            }
            SO_Instant_Alerts::store_browser_alert(array(
                'id'              => time(),
                'title'           => 'إنذار قبل الحدث — ' . ($alert['prediction']['actor'] ?? 'غير محدد'),
                'actor_v2'        => $alert['prediction']['actor'] ?? 'غير محدد',
                'region'          => $alert['prediction']['region'] ?? 'غير محدد',
                'intel_type'      => 'إنذار مبكر',
                'score'           => (int) ($alert['probability'] ?? 0),
                'event_timestamp' => time(),
            ));
        }
    }
}

if (!function_exists('so_maybe_run_threat_intel_ai')) {
    function so_maybe_run_threat_intel_ai() {
        $events = so_ti_recent_events(150);
        if (empty($events)) return null;
        $alert = so_early_warning_engine($events);
        so_dispatch_early_warning($alert);
        update_option('so_last_ti_ai_snapshot', $alert, false);
        return $alert;
    }
}

// تشغيل دوري للإنذار المبكر
if (!has_action('so_instant_alerts_cron', 'so_maybe_run_threat_intel_ai')) {
    add_action('so_instant_alerts_cron', 'so_maybe_run_threat_intel_ai', 20);
}

// ==========================================================================
// الكلاس الرئيسي للإضافة
// ==========================================================================

if (!class_exists('Beiruttime_OSINT_Pro')) {

class Beiruttime_OSINT_Pro {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // يمكن إضافة hooks هنا عند الحاجة
    }

    /**
     * تسجيل القوائم في لوحة التحكم
     */
    public static function register_menus() {
        add_menu_page(
            'Beiruttime OSINT Pro',
            'Beiruttime OSINT Pro',
            'manage_options',
            'bt_osint_dashboard',
            array(__CLASS__, 'page_dashboard'),
            'dashicons-chart-line',
            30
        );

        add_submenu_page('bt_osint_dashboard', 'لوحة القيادة', '🏠 لوحة القيادة', 'manage_options', 'bt_osint_dashboard', array(__CLASS__, 'page_dashboard'));
        add_submenu_page('bt_osint_dashboard', 'سجل الأخبار', '📰 سجل الأخبار', 'manage_options', 'bt_osint_logs', array(__CLASS__, 'page_logs'));
        add_submenu_page('bt_osint_dashboard', 'إعادة بناء الأرشفة', '🔄 إعادة بناء الأرشفة', 'manage_options', 'bt_osint_reindex', array(__CLASS__, 'page_reindex'));
        add_submenu_page('bt_osint_dashboard', 'الإعدادات', '⚙️ الإعدادات', 'manage_options', 'bt_osint_settings', array(__CLASS__, 'page_settings'));
    }

    /**
     * صفحة إعادة بناء الأرشفة
     */
    public static function page_reindex() {
        self::admin_wrap_open('🔄 إعادة بناء الأرشفة - تحليل الحرب المركبة');

        global $wpdb;
        $table_name = $wpdb->prefix . 'so_news_events';
        $message = '';
        $stats = array();

        // معالجة الطلب
        if (isset($_POST['bt_start_reindex']) && check_admin_referer('bt_reindex_action', 'bt_reindex_nonce')) {
            $limit = isset($_POST['batch_limit']) ? intval($_POST['batch_limit']) : 100;
            if ($limit < 1) $limit = 100;
            if ($limit > 500) $limit = 500;

            echo '<div style="background:#fff; padding:20px; border-radius:8px; margin-top:20px;">';
            echo '<h3>جاري المعالجة...</h3>';

            $processed = 0;
            $errors = 0;

            $events = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, title, war_data, actor_v2, region FROM {$table_name} WHERE threat_score = 0 OR threat_score IS NULL ORDER BY id DESC LIMIT %d",
                    $limit
                )
            );

            if (!empty($events)) {
                foreach ($events as $event) {
                    try {
                        $event_data = array(
                            'title'    => $event->title,
                            'war_data' => $event->war_data,
                            'actor_v2' => $event->actor_v2,
                            'region'   => $event->region,
                        );

                        if (function_exists('sod_enhance_event_with_hybrid_analysis')) {
                            $enhanced_data = sod_enhance_event_with_hybrid_analysis($event_data);
                            if (is_array($enhanced_data) && !empty($enhanced_data)) {
                                $wpdb->update($table_name, $enhanced_data, array('id' => $event->id));
                                $processed++;
                            } else {
                                $errors++;
                            }
                        } else {
                            $errors++;
                            error_log('Reindex Error: function sod_enhance_event_with_hybrid_analysis does not exist');
                        }
                    } catch (Throwable $e) {
                        $errors++;
                        error_log('Reindex Error: ' . $e->getMessage());
                    }
                }
                echo "<p style='color:green'><strong>تمت معالجة {$processed} حدث بنجاح.</strong> (أخطاء: {$errors})</p>";
                echo '<a href="' . esc_url(admin_url('admin.php?page=bt_osint_reindex')) . '" class="button">متابعة المعالجة</a>';
            } else {
                echo "<p style='color:blue'>لا توجد أحداث متبقية للمعالجة!</p>";
            }
            echo '</div>';
        } else {
            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE threat_score = 0 OR threat_score IS NULL");
            ?>
            <div style="background:#fff; padding:25px; border-radius:8px;">
                <h2>🛠️ إعادة بناء الأرشفة الاستخباراتية</h2>
                <p>إعادة تحليل الأحداث القديمة باستخدام محركات الحرب المركبة.</p>
                <div style="background:#f0f0f1; padding:15px; margin:15px 0; border-radius:5px;">
                    <strong>الإحصائيات:</strong><br>
                    إجمالي الأحداث: <?php echo number_format($total); ?><br>
                    بانتظار التحليل: <?php echo number_format($pending); ?>
                </div>
                <form method="post">
                    <?php wp_nonce_field('bt_reindex_action', 'bt_reindex_nonce'); ?>
                    <label>حجم الدفعة: <input type="number" name="batch_limit" value="100" min="1" max="500"></label>
                    <input type="submit" name="bt_start_reindex" class="button button-primary" value="🚀 بدء المعالجة">
                </form>
            </div>
            <?php
        }

        self::admin_wrap_close();
    }

    public static function page_dashboard() {
        self::admin_wrap_open('لوحة القيادة');
        echo '<h1>مرحباً بك في Beiruttime OSINT Pro</h1>';
        
        // عرض حالة الذكاء الاستخباراتي
        $alert = get_option('so_last_ti_ai_snapshot', array());
        if (!empty($alert) && is_array($alert)) {
            echo '<div style="background:#1e293b;color:#fff;padding:20px;border-radius:12px;margin-top:20px;">';
            echo '<h3>🧠 حالة التهديد الحالية</h3>';
            echo '<p>مستوى الخطر: <strong>' . esc_html($alert['level'] ?? 'غير محدد') . '</strong></p>';
            echo '<p>احتمال التصعيد: <strong>' . intval($alert['probability'] ?? 0) . '%</strong></p>';
            echo '</div>';
        }
        
        self::admin_wrap_close();
    }

    public static function page_logs() {
        self::admin_wrap_open('سجل الأخبار');
        echo '<h1>سجل الأخبار</h1>';
        echo '<p>سيتم عرض جدول الأحداث هنا.</p>';
        self::admin_wrap_close();
    }

    public static function page_settings() {
        self::admin_wrap_open('الإعدادات');
        echo '<h1>الإعدادات</h1>';
        echo '<p>خيارات التكوين ستظهر هنا.</p>';
        self::admin_wrap_close();
    }

    public static function admin_wrap_open($title) {
        echo '<div class="wrap"><h1>' . esc_html($title) . '</h1><div style="background:#fff; padding:20px; margin-top:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">';
    }

    public static function admin_wrap_close() {
        echo '</div></div>';
    }
}

// تسجيل القوائم عند تحميل الإضافة
add_action('admin_menu', array('Beiruttime_OSINT_Pro', 'register_menus'));

} // نهاية class_exists

// ==========================================================================
// Shortcode لعرض حالة التهديد
// ==========================================================================

if (!function_exists('so_threat_ai_shortcode')) {
    add_shortcode('osint_threat_ai', function(){
        $alert = get_option('so_last_ti_ai_snapshot', array());
        if (empty($alert) || !is_array($alert)) {
            $alert = so_maybe_run_threat_intel_ai();
        }
        if (empty($alert) || !is_array($alert)) return "لا توجد بيانات";

        ob_start(); ?>
        <div style="background:#0f172a;color:#fff;padding:20px;border-radius:12px">
            <h2>🧠 Threat Intelligence AI</h2>
            <p>مستوى الخطر: <strong><?php echo esc_html($alert['level'] ?? 'غير محدد'); ?></strong></p>
            <p>احتمال التصعيد: <strong><?php echo intval($alert['probability'] ?? 0); ?>%</strong></p>
            <p>الفاعل المتوقع: <strong><?php echo esc_html($alert['prediction']['actor'] ?? 'غير محدد'); ?></strong></p>
            <p>المنطقة المتوقعة: <strong><?php echo esc_html($alert['prediction']['region'] ?? 'غير محدد'); ?></strong></p>
            <p>الوقت المرجح: <strong><?php echo esc_html($alert['prediction']['time'] ?? date('H')); ?>:00</strong></p>
            
            <?php if (!empty($alert['scenarios'])): ?>
                <hr style="border-color:rgba(255,255,255,.08)">
                <h3>🎯 السيناريوهات الأبرز</h3>
                <?php foreach ((array)$alert['scenarios'] as $s): ?>
                    <div style="margin-bottom:10px;padding:10px;background:rgba(255,255,255,.04);border-radius:10px">
                        <strong><?php echo esc_html($s['title'] ?? ''); ?></strong><br>
                        <?php echo esc_html($s['description'] ?? ''); ?><br>
                        <small>الثقة: <?php echo esc_html($s['confidence'] ?? 'غير محدد'); ?></small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    });
}

// ==========================================================================
// تحسينات واجهة الإدارة
// ==========================================================================

if (!function_exists('so_ma_admin_footer_panel')) {
    function so_ma_admin_footer_panel() {
        if (!is_admin()) return;
        if (!isset($_GET['page'])) return;
        $page = sanitize_text_field((string)$_GET['page']);
        if (strpos($page, 'osint') === false && strpos($page, 'strategic') === false && strpos($page, 'beiruttime') === false) return;

        echo '<script>
        document.addEventListener("DOMContentLoaded", function(){
            var table = document.querySelector("table.widefat, .wp-list-table");
            if(!table) return;
            var headRow = table.querySelector("thead tr");
            if(headRow && !headRow.querySelector(".column-so_ma_primary")){
                ["الفاعل الرئيسي","الهدف","السياق","النية"].forEach(function(txt, idx){
                    var th=document.createElement("th");
                    th.className=["column-so_ma_primary","column-so_ma_target","column-so_ma_context","column-so_ma_intent"][idx];
                    th.textContent=txt;
                    headRow.appendChild(th);
                });
            }
            table.querySelectorAll("tbody tr").forEach(function(tr){
                if(tr.querySelector(".so-ma-dyn")) return;
                for(var i=0;i<4;i++){
                    var td=document.createElement("td");
                    td.className="so-ma-dyn";
                    td.textContent="—";
                    tr.appendChild(td);
                }
            });
        });
        </script>';
    }
    add_action('admin_footer', 'so_ma_admin_footer_panel', 99);
}
