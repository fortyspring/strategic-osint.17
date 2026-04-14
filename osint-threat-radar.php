<?php
/**
 * Beiruttime OSINT - Threat Radar & Relations Dashboard
 * 
 * إضافة قسم مؤشرات ورادار التهديدات للحرب المركبة
 * ومؤشر تفاعلي للعلاقات والوكلاء والأعمال والأنشطة
 * 
 * @package Beiruttime\OSINT
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * رادار التهديدات - عرض بصري للطبقات النشطة
 */
function sod_render_threat_radar() {
    global $wpdb;
    $table = $wpdb->prefix . 'so_news_events';
    
    // جلب إحصائيات الطبقات في آخر 24 ساعة
    $hours = 24;
    $since = time() - ($hours * 3600);
    
    $layers_stats = $wpdb->get_results("
        SELECT 
            osint_type,
            COUNT(*) as count,
            AVG(threat_score) as avg_threat,
            AVG(multi_domain_score) as avg_multi
        FROM {$table}
        WHERE event_timestamp >= {$since}
        AND osint_type IS NOT NULL AND osint_type != ''
        GROUP BY osint_type
        ORDER BY count DESC
    ", ARRAY_A);
    
    // جلب الأحداث عالية التهديد
    $critical_events = $wpdb->get_results("
        SELECT id, title, threat_score, hybrid_layers, primary_actor, region
        FROM {$table}
        WHERE threat_score >= 70
        AND event_timestamp >= {$since}
        ORDER BY threat_score DESC
        LIMIT 10
    ", ARRAY_A);
    
    // جلب شبكة الفاعلين النشطين
    $active_actors = $wpdb->get_results("
        SELECT primary_actor, COUNT(*) as actions_count, AVG(threat_score) as avg_threat
        FROM {$table}
        WHERE primary_actor IS NOT NULL AND primary_actor != ''
        AND event_timestamp >= {$since}
        GROUP BY primary_actor
        HAVING actions_count >= 2
        ORDER BY actions_count DESC
        LIMIT 15
    ", ARRAY_A);
    
    ob_start();
    ?>
    <style>
        .threat-radar-wrap { font-family: 'Tajawal', sans-serif; direction: rtl; }
        .radar-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .radar-card { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 12px; padding: 20px; border: 1px solid rgba(255,255,255,0.1); }
        .radar-card-header { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
        .radar-icon { font-size: 24px; }
        .radar-title { font-size: 18px; font-weight: bold; color: #fff; }
        .layer-bar { display: flex; align-items: center; margin-bottom: 10px; }
        .layer-name { width: 120px; font-size: 13px; color: #94a3b8; }
        .layer-progress { flex: 1; height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden; }
        .layer-fill { height: 100%; border-radius: 4px; transition: width 0.5s ease; }
        .layer-count { width: 40px; text-align: left; font-size: 12px; color: #fff; font-weight: bold; }
        .fill-military { background: linear-gradient(90deg, #dc2626, #ef4444); }
        .fill-security { background: linear-gradient(90deg, #2563eb, #3b82f6); }
        .fill-cyber { background: linear-gradient(90deg, #7c3aed, #8b5cf6); }
        .fill-political { background: linear-gradient(90deg, #059669, #10b981); }
        .fill-economic { background: linear-gradient(90deg, #d97706, #f59e0b); }
        .fill-social { background: linear-gradient(90deg, #db2777, #ec4899); }
        .fill-media { background: linear-gradient(90deg, #6366f1, #818cf8); }
        .fill-energy { background: linear-gradient(90deg, #0891b2, #06b6d4); }
        .fill-geostrategic { background: linear-gradient(90deg, #475569, #64748b); }
        
        .events-list { list-style: none; padding: 0; margin: 0; }
        .event-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .event-item:last-child { border-bottom: none; }
        .event-title { font-size: 13px; color: #e2e8f0; flex: 1; }
        .event-score { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .score-critical { background: rgba(220, 38, 38, 0.2); color: #dc2626; }
        .score-high { background: rgba(249, 115, 22, 0.2); color: #f97316; }
        
        .actor-network { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .actor-node { background: rgba(255,255,255,0.05); border-radius: 8px; padding: 15px; text-align: center; border: 1px solid rgba(255,255,255,0.1); }
        .actor-name { font-size: 13px; color: #fff; margin-bottom: 8px; font-weight: bold; }
        .actor-stats { display: flex; justify-content: center; gap: 15px; font-size: 11px; color: #94a3b8; }
        .stat-value { font-weight: bold; color: #60a5fa; }
        
        .relations-graph { background: rgba(0,0,0,0.3); border-radius: 12px; padding: 20px; margin-top: 20px; min-height: 400px; }
        .graph-title { font-size: 16px; font-weight: bold; color: #fff; margin-bottom: 15px; }
    </style>
    
    <div class="threat-radar-wrap">
        <!-- رادار الطبقات -->
        <div class="radar-grid">
            <div class="radar-card">
                <div class="radar-card-header">
                    <span class="radar-icon">🎯</span>
                    <span class="radar-title">رادار طبقات الحرب المركبة</span>
                </div>
                <?php if (!empty($layers_stats)): ?>
                    <?php foreach ($layers_stats as $layer): 
                        $layer_key = $layer['osint_type'];
                        $fill_class = 'fill-' . $layer_key;
                        $width = min(100, ($layer['count'] / max(1, max(array_column($layers_stats, 'count')))) * 100);
                        $layer_name_ar = sod_get_layer_name_ar($layer_key);
                    ?>
                    <div class="layer-bar">
                        <span class="layer-name"><?php echo esc_html($layer_name_ar); ?></span>
                        <div class="layer-progress">
                            <div class="layer-fill <?php echo esc_attr($fill_class); ?>" style="width: <?php echo esc_attr($width); ?>%"></div>
                        </div>
                        <span class="layer-count"><?php echo esc_html($layer['count']); ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #64748b; text-align: center;">لا توجد بيانات متاحة</p>
                <?php endif; ?>
            </div>
            
            <!-- الأحداث الحرجة -->
            <div class="radar-card">
                <div class="radar-card-header">
                    <span class="radar-icon">🚨</span>
                    <span class="radar-title">أحداث عالية التهديد</span>
                </div>
                <?php if (!empty($critical_events)): ?>
                    <ul class="events-list">
                        <?php foreach ($critical_events as $event): 
                            $score_class = $event['threat_score'] >= 80 ? 'score-critical' : 'score-high';
                        ?>
                        <li class="event-item">
                            <span class="event-title"><?php echo esc_html(sod_safe_substr($event['title'], 0, 60)); ?></span>
                            <span class="event-score <?php echo esc_attr($score_class); ?>">
                                <?php echo esc_html($event['threat_score']); ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="color: #64748b; text-align: center;">لا توجد أحداث حرجة</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- شبكة الفاعلين -->
        <div class="radar-card" style="margin-bottom: 20px;">
            <div class="radar-card-header">
                <span class="radar-icon">🕸️</span>
                <span class="radar-title">شبكة الفاعلين والنشاطات</span>
            </div>
            <?php if (!empty($active_actors)): ?>
                <div class="actor-network">
                    <?php foreach ($active_actors as $actor): ?>
                    <div class="actor-node">
                        <div class="actor-name"><?php echo esc_html(sod_safe_substr($actor['primary_actor'], 0, 30)); ?></div>
                        <div class="actor-stats">
                            <span>عمليات: <span class="stat-value"><?php echo esc_html($actor['actions_count']); ?></span></span>
                            <span>تهديد: <span class="stat-value"><?php echo esc_html(round($actor['avg_threat'])); ?></span></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: #64748b; text-align: center;">لا توجد بيانات فاعلين</p>
            <?php endif; ?>
        </div>
        
        <!-- رسم بياني للعلاقات -->
        <div class="relations-graph">
            <div class="graph-title">🔗 خريطة العلاقات الاستخباراتية</div>
            <div id="relations-canvas" style="height: 350px; display: flex; align-items: center; justify-content: center; color: #64748b;">
                <p>سيتم تطوير الرسم البياني التفاعلي في التحديث القادم</p>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * الحصول على اسم الطبقة بالعربية
 */
function sod_get_layer_name_ar($layer_key) {
    $names = [
        'military' => 'العسكرية',
        'security' => 'الأمنية',
        'cyber' => 'السيبرانية',
        'political' => 'السياسية',
        'economic' => 'الاقتصادية',
        'social' => 'الاجتماعية',
        'media_psychological' => 'الإعلامية',
        'energy' => 'الطاقة',
        'geostrategic' => 'الجيوستراتيجية'
    ];
    return $names[$layer_key] ?? $layer_key;
}

/**
 * قص النص بأمان (تجنب إعادة التعريف إذا كانت موجودة)
 */
if (!function_exists('sod_safe_substr')) {
    function sod_safe_substr($text, $start, $length) {
        if (empty($text)) return '';
        return mb_substr(strip_tags($text), $start, $length, 'UTF-8');
    }
}

/**
 * Shortcode لرصد التهديدات
 */
add_shortcode('sod_threat_radar', 'sod_render_threat_radar');

/**
 * تكامل مع لوحة PowerBI
 */
function sod_add_threat_radar_to_powerbi($content) {
    $radar_section = '<div style="margin: 30px 0;">' . sod_render_threat_radar() . '</div>';
    return $content . $radar_section;
}
add_filter('sod_powerbi_content', 'sod_add_threat_radar_to_powerbi');
