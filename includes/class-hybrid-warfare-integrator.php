<?php
/**
 * Beiruttime OSINT - Hybrid Warfare Integration Layer
 * 
 * طبقة التكامل بين محركات الحرب المركبة وسير المعالجة الرئيسي
 * تدمج التحليلات المتقدمة في تدفق معالجة الأحداث
 * 
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

class SO_Hybrid_Warfare_Integrator {
    
    /**
     * دمج تحليل الحرب المركبة في حدث قبل الإدخال
     * 
     * @param array $event_data بيانات الحدث الخام
     * @return array بيانات الحدث المحسنة
     */
    public static function enhance_before_insert(array $event_data): array {
        // استخدام محرك HybridWarfareEngine إذا كان متاحاً
        if (class_exists('\\Beiruttime\\OSINT\\Services\\HybridWarfareEngine')) {
            $engine = \Beiruttime\OSINT\Services\HybridWarfareEngine::instance();
            return $engine->enhanceEvent($event_data);
        }
        
        // استخدام الدوال الوظيفية من ملف التحديث
        if (function_exists('sod_enhance_event_with_hybrid_analysis')) {
            return sod_enhance_event_with_hybrid_analysis($event_data);
        }
        
        // تحليل أساسي إذا لم تكن المحركات متاحة
        return self::basic_hybrid_analysis($event_data);
    }
    
    /**
     * تحليل هجين أساسي
     */
    private static function basic_hybrid_analysis(array $event_data): array {
        $title = $event_data['title'] ?? '';
        $content = $event_data['war_data'] ?? $event_data['description'] ?? '';
        $full_text = $title . ' ' . $content;
        
        $layers = [];
        $layer_keywords = [
            'military' => ['غارة', 'قصف', 'استهداف', 'صاروخ', 'مسيّرة'],
            'security' => ['اعتقال', 'مداهمة', 'أمني'],
            'cyber' => ['اختراق', 'سيبراني', 'رقمي'],
            'political' => ['تصريح', 'بيان', 'عقوبات'],
            'economic' => ['نفط', 'غاز', 'اقتصاد', 'ميناء'],
            'energy' => ['كهرباء', 'محطة', 'طاقة']
        ];
        
        foreach ($layer_keywords as $layer => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_stripos($full_text, $keyword) !== false) {
                    $layers[] = $layer;
                    break;
                }
            }
        }
        
        $layers = array_unique($layers);
        $score = (int)($event_data['score'] ?? 0);
        $threat_score = min(100, intval($score * 0.4) + (count($layers) * 10));
        
        return array_merge($event_data, [
            'hybrid_layers' => json_encode([
                'primary_layer' => !empty($layers) ? reset($layers) : 'general',
                'active_layers' => $layers,
                'is_hybrid' => count($layers) >= 2
            ], JSON_UNESCAPED_UNICODE),
            'osint_type' => (!empty($layers) ? reset($layers) : 'general') . '_event',
            'multi_domain_score' => (float)(count($layers) * 15),
            'threat_score' => $threat_score,
            'escalation_score' => min(100, intval($score * 0.3)),
            'confidence_score' => 50,
            'risk_level' => $threat_score >= 60 ? 'مرتفع' : ($threat_score >= 30 ? 'متوسط' : 'منخفض'),
            'alert_flag' => $threat_score >= 60 ? 1 : 0,
            'verification_status' => 'غير مؤكد'
        ]);
    }
    
    /**
     * تحديث حدث موجود بتحليل الحرب المركبة
     * 
     * @param int $event_id معرف الحدث
     * @return bool نجاح العملية
     */
    public static function update_existing_event(int $event_id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'so_news_events';
        
        $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $event_id), ARRAY_A);
        if (!$event) return false;
        
        $enhanced = self::enhance_before_insert($event);
        
        $update_data = [
            'hybrid_layers' => $enhanced['hybrid_layers'] ?? null,
            'osint_type' => $enhanced['osint_type'] ?? 'عام',
            'multi_domain_score' => $enhanced['multi_domain_score'] ?? 0,
            'threat_score' => $enhanced['threat_score'] ?? 0,
            'escalation_score' => $enhanced['escalation_score'] ?? 0,
            'confidence_score' => $enhanced['confidence_score'] ?? 0,
            'risk_level' => $enhanced['risk_level'] ?? 'منخفض',
            'alert_flag' => $enhanced['alert_flag'] ?? 0,
            'verification_status' => $enhanced['verification_status'] ?? 'غير مؤكد'
        ];
        
        $result = $wpdb->update($table, $update_data, ['id' => $event_id]);
        return $result !== false;
    }
    
    /**
     * معالجة دفعة من الأحداث القديمة
     * 
     * @param int $limit عدد الأحداث للمعالجة
     * @return array إحصائيات المعالجة
     */
    public static function batch_process_old_events(int $limit = 100): array {
        global $wpdb;
        $table = $wpdb->prefix . 'so_news_events';
        
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, war_data, score FROM {$table} 
             WHERE hybrid_layers IS NULL OR hybrid_layers = '' 
             ORDER BY event_timestamp DESC 
             LIMIT %d",
            $limit
        ), ARRAY_A);
        
        $stats = ['processed' => 0, 'failed' => 0];
        
        foreach ($events as $event) {
            if (self::update_existing_event($event['id'])) {
                $stats['processed']++;
            } else {
                $stats['failed']++;
            }
        }
        
        so_log("تمت معالجة {$stats['processed']} حدث قديم بتحليل الحرب المركبة", 'BATCH_UPDATE');
        return $stats;
    }
    
    /**
     * استخراج علاقات مرجعية بين الكيانات
     * 
     * @param array $event_data بيانات الحدث
     * @return array العلاقات المستخرجة
     */
    public static function extract_entity_relationships(array $event_data): array {
        $relationships = [];
        
        $actor = $event_data['actor_v2'] ?? '';
        $target = $event_data['target_v2'] ?? '';
        $region = $event_data['region'] ?? '';
        $weapon = $event_data['weapon_v2'] ?? '';
        
        if ($actor && $target) {
            $relationships[] = [
                'type' => 'actor_target',
                'source' => $actor,
                'target' => $target,
                'strength' => 0.8
            ];
        }
        
        if ($actor && $region) {
            $relationships[] = [
                'type' => 'actor_region',
                'source' => $actor,
                'target' => $region,
                'strength' => 0.6
            ];
        }
        
        if ($actor && $weapon) {
            $relationships[] = [
                'type' => 'actor_weapon',
                'source' => $actor,
                'target' => $weapon,
                'strength' => 0.7
            ];
        }
        
        // استخدام دالة استخراج شبكة الفاعلين إذا كانت متاحة
        if (function_exists('sod_extract_actor_network')) {
            $network = sod_extract_actor_network(
                $event_data['title'] ?? '',
                $event_data['war_data'] ?? ''
            );
            
            if (!empty($network['sponsors'])) {
                foreach ($network['sponsors'] as $sponsor) {
                    $relationships[] = [
                        'type' => 'sponsorship',
                        'source' => $sponsor,
                        'target' => $actor,
                        'strength' => 0.9
                    ];
                }
            }
        }
        
        return $relationships;
    }
    
    /**
     * حفظ العلاقات في قاعدة البيانات
     * 
     * @param int $event_id معرف الحدث
     * @param array $relationships العلاقات المستخرجة
     * @return bool نجاح الحفظ
     */
    public static function save_relationships(int $event_id, array $relationships): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'so_entity_relations';
        
        // إنشاء الجدول إذا لم يكن موجوداً
        self::ensure_relations_table_exists();
        
        foreach ($relationships as $rel) {
            $wpdb->insert($table, [
                'event_id' => $event_id,
                'relation_type' => $rel['type'],
                'source_entity' => $rel['source'],
                'target_entity' => $rel['target'],
                'strength' => $rel['strength'],
                'created_at' => time()
            ]);
        }
        
        return true;
    }
    
    /**
     * التأكد من وجود جدول العلاقات
     */
    private static function ensure_relations_table_exists(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'so_entity_relations';
        
        $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
        
        if (!$exists) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE {$table} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                event_id bigint(20) NOT NULL,
                relation_type varchar(50) NOT NULL,
                source_entity text NOT NULL,
                target_entity text NOT NULL,
                strength float DEFAULT 0.5,
                created_at bigint(20) NOT NULL,
                PRIMARY KEY (id),
                KEY event_id (event_id),
                KEY relation_type (relation_type),
                KEY source_target (source_entity(100), target_entity(100))
            ) {$charset_collate};";
            
            dbDelta($sql);
            so_log("تم إنشاء جدول so_entity_relations", 'SCHEMA');
        }
    }
}

// Hook لتفعيل المعالجة الهجينة عند إدراج حدث جديد
add_action('so_reclassify_event_after_insert', function($event_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'so_news_events';
    
    $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $event_id), ARRAY_A);
    if ($event) {
        // تحديث الحدث بتحليل الحرب المركبة
        SO_Hybrid_Warfare_Integrator::update_existing_event($event_id);
        
        // استخراج وحفظ العلاقات
        $relationships = SO_Hybrid_Warfare_Integrator::extract_entity_relationships($event);
        SO_Hybrid_Warfare_Integrator::save_relationships($event_id, $relationships);
    }
});
