<?php
/**
 * Beiruttime OSINT - Batch Reindexing Service
 * 
 * خدمة إعادة معالجة الأخبار القديمة لتصنيفها وفق منطق الحرب المركبة
 * وتحديث الحقول الاستخباراتية المتقدمة.
 * 
 * @package Beiruttime\OSINT\Services
 * @version 1.0.0
 */

namespace Beiruttime\OSINT\Services;

use WordPress;
use WP_Query;
use WP_CLI;

class Batch_Reindexer {
    
    /**
     * اسم الجدول
     */
    private $table_name;
    
    /**
     * سجل العمليات
     */
    private $log = [];
    
    /**
     * إحصائيات العملية
     */
    private $stats = [
        'processed' => 0,
        'updated' => 0,
        'errors' => 0,
        'start_time' => 0,
        'end_time' => 0
    ];

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'so_news_events';
    }

    /**
     * بدء عملية إعادة المعالجة
     * 
     * @param int $limit عدد الأحداث في كل دفعة
     * @param int $offset نقطة البداية
     * @param bool $dry_run تجربة بدون حفظ
     * @return array نتائج العملية
     */
    public function run_batch($limit = 100, $offset = 0, $dry_run = false) {
        global $wpdb;
        
        $this->stats['start_time'] = microtime(true);
        
        // جلب الأحداث التي لم تُحلل بعد أو تحتاج تحديث
        $events = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, war_data, actor_v2, region, score, osint_type, hybrid_layers 
                 FROM {$this->table_name} 
                 WHERE (hybrid_layers IS NULL OR hybrid_layers = '' OR threat_score = 0)
                 ORDER BY event_timestamp DESC 
                 LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );

        if (empty($events)) {
            return ['status' => 'completed', 'message' => 'لا توجد أحداث للمعالجة', 'stats' => $this->stats];
        }

        // تحميل محركات التحليل إذا لم تكن محملة
        if (!class_exists('Beiruttime\OSINT\Services\Hybrid_Warfare_Engine')) {
            require_once __DIR__ . '/class-hybrid-warfare-engine.php';
        }
        
        $hybrid_engine = \Beiruttime\OSINT\Services\Hybrid_Warfare_Engine::instance();

        foreach ($events as $event) {
            try {
                $this->stats['processed']++;
                
                // تحضير البيانات للتحليل
                $event_data = [
                    'title' => $event['title'],
                    'war_data' => $event['war_data'],
                    'actor_v2' => $event['actor_v2'],
                    'region' => $event['region'],
                    'score' => $event['score']
                ];

                // 1. تحليل طبقات الحرب المركبة
                $hybrid_analysis = $hybrid_engine->analyze_layers($event_data);
                
                // 2. حساب Scores المتقدم
                $scores = $this->calculate_scores($event_data, $hybrid_analysis);
                
                // 3. استخراج شبكة الفاعلين
                $actor_network = $this->extract_actor_network($event_data);

                if (!$dry_run) {
                    // تحديث السجل في قاعدة البيانات
                    $update_data = array_merge(
                        $hybrid_analysis,
                        $scores,
                        $actor_network,
                        ['reindexed_at' => current_time('mysql')]
                    );

                    $wpdb->update(
                        $this->table_name,
                        $update_data,
                        ['id' => $event['id']],
                        array_fill_keys(array_keys($update_data), '%s'),
                        ['id' => '%d']
                    );

                    if ($wpdb->last_error) {
                        throw new \Exception($wpdb->last_error);
                    }
                    
                    $this->stats['updated']++;
                } else {
                    $this->stats['updated']++; // في وضع التجربة نعتبرها محدثة
                }

            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->log[] = [
                    'event_id' => $event['id'],
                    'error' => $e->getMessage(),
                    'time' => current_time('mysql')
                ];
                
                error_log("Beiruttime OSINT Reindex Error [Event ID: {$event['id']}]: " . $e->getMessage());
            }
        }

        $this->stats['end_time'] = microtime(true);
        
        return [
            'status' => 'partial',
            'message' => sprintf('تم معالجة %d حدث، تحديث %d، أخطاء %d', 
                $this->stats['processed'], 
                $this->stats['updated'], 
                $this->stats['errors']
            ),
            'stats' => $this->stats,
            'logs' => array_slice($this->log, -10) // آخر 10 أخطاء فقط
        ];
    }

    /**
     * حساب مؤشراتScores للحدث
     */
    private function calculate_scores($event_data, $hybrid_analysis) {
        // محاكاة مبسطة لحساب Scores (يمكن ربطها بالمحرك الحقيقي)
        $layers_count = !empty($hybrid_analysis['hybrid_layers']) ? count(json_decode($hybrid_analysis['hybrid_layers'], true)) : 0;
        
        $threat_score = min(100, ($layers_count * 15) + (stripos($event_data['title'], 'استهداف') !== false ? 20 : 0));
        $escalation_score = min(100, ($layers_count * 10) + (stripos($event_data['title'], 'تصعيد') !== false ? 30 : 0));
        $confidence_score = 75; // افتراضي
        
        return [
            'threat_score' => $threat_score,
            'escalation_score' => $escalation_score,
            'confidence_score' => $confidence_score,
            'risk_level' => $threat_score > 70 ? 'حرج' : ($threat_score > 40 ? 'متوسط' : 'منخفض'),
            'multi_domain_score' => $layers_count * 10
        ];
    }

    /**
     * استخراج شبكة الفاعلين بشكل مبسط
     */
    private function extract_actor_network($event_data) {
        $actor = $event_data['actor_v2'] ?? '';
        $network = [];
        
        if (!empty($actor)) {
            $network['primary'] = $actor;
            // يمكن إضافة منطق ذكي هنا لاستخراج الراعي والممول من النص
        }
        
        return [
            'primary_actor' => $actor,
            'actor_network' => !empty($network) ? json_encode($network, JSON_UNESCAPED_UNICODE) : null
        ];
    }

    /**
     * معالجة كاملة للأرشيف (تعمل عبر دفعات متتالية)
     * 
     * @param int $total_limit الحد الأقصى الإجمالي
     * @param bool $dry_run
     */
    public function process_full_archive($total_limit = 10000, $dry_run = false) {
        $batch_size = 100;
        $offset = 0;
        $total_processed = 0;
        
        while ($total_processed < $total_limit) {
            $result = $this->run_batch($batch_size, $offset, $dry_run);
            
            if ($result['status'] === 'completed' || empty($result['stats']['processed'])) {
                break;
            }
            
            $total_processed += $result['stats']['processed'];
            $offset += $batch_size;
            
            // وقت راحة قصير لتجنب ضغط القاعدة
            usleep(500000); 
        }
        
        return $this->stats;
    }
}

// تكامل مع WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('beiruttime reindex', function($args, $assoc_args) {
        $limit = isset($assoc_args['limit']) ? intval($assoc_args['limit']) : 1000;
        $dry_run = isset($assoc_args['dry-run']);
        
        \WP_CLI::line("بدء إعادة معالجة الأرشفة (الحد: $limit، تجربة: " . ($dry_run ? 'نعم' : 'لا') . ")...");
        
        $reindexer = new Batch_Reindexer();
        $stats = $reindexer->process_full_archive($limit, $dry_run);
        
        \WP_CLI::success("اكتملت العملية!");
        \WP_CLI::line("تمت معالجة: {$stats['processed']}");
        \WP_CLI::line("تم التحديث: {$stats['updated']}");
        \WP_CLI::line("أخطاء: {$stats['errors']}");
        \WP_CLI::line("الزمن المستغرق: " . round($stats['end_time'] - $stats['start_time'], 2) . " ثانية");
    });
}
