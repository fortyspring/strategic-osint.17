<?php
/**
 * Beiruttime OSINT - Hybrid Warfare Analysis Engine
 * 
 * محرك تحليل طبقات الحرب المركبة المتقدم
 * يدمج مع سير المعالجة الرئيسي للنظام
 * 
 * @version 1.0.0
 * @package Beiruttime\OSINT\Services
 */

namespace Beiruttime\OSINT\Services;

if (!defined('ABSPATH')) exit;

class HybridWarfareEngine {
    
    use \Beiruttime\OSINT\Traits\Singleton;
    
    /**
     * قاموس طبقات الحرب المركبة
     */
    private $layers_dictionary = [];
    
    /**
     * تهيئة المحرك
     */
    private function __construct() {
        $this->initialize_layers_dictionary();
    }
    
    /**
     * تهيئة قاموس الطبقات
     */
    private function initialize_layers_dictionary() {
        $this->layers_dictionary = [
            'military' => [
                'name' => 'الطبقة العسكرية',
                'keywords' => ['غارة', 'قصف', 'استهداف', 'هجوم', 'اشتباك', 'إغارة', 'غارات', 'صاروخ', 'مسيّرة', 'طائرة', 'دبابة', 'قوات', 'توغل', 'اغتيال', 'كمين', 'اشتباك', 'قصف مدفعي', 'إطلاق نار', 'انسحاب', 'تعزيزات', 'تحشيد', 'مناورة'],
                'weight' => 1.0
            ],
            'security' => [
                'name' => 'الطبقة الأمنية',
                'keywords' => ['اعتقال', 'مداهمة', 'تفكيك', 'خلية', 'أمني', 'استخبارات', 'تجسس', 'تسلل', 'حاجز', 'إغلاق', 'منطقة محظورة', 'رفع الجاهزية', 'مكافحة', 'ضبط', 'شحنة'],
                'weight' => 0.9
            ],
            'cyber' => [
                'name' => 'الطبقة السيبرانية',
                'keywords' => ['اختراق', 'سيبراني', 'رقمي', 'تسريب', 'بيانات', 'موقع إلكتروني', 'تعطيل', 'حجب', 'قرصنة', 'هاكر', 'نظام معلومات', 'برمجيات', 'هجوم إلكتروني', 'تشويش', 'تشويه رقمي'],
                'weight' => 0.85
            ],
            'geographic' => [
                'name' => 'الطبقة الجغرافية والفضائية',
                'keywords' => ['قمر صناعي', 'صورة فضائية', 'تحصينات', 'خندق', 'موقع جديد', 'تدمير', 'آثار القصف', 'إحداثيات', 'خرائط', 'تضاريس', 'مراقبة جوية', 'مسح'],
                'weight' => 0.8
            ],
            'political' => [
                'name' => 'الطبقة السياسية والدبلوماسية',
                'keywords' => ['تصريح', 'بيان', 'عقوبات', 'اتفاق', 'مفاوضات', 'وفد', 'زيارة', 'سفير', 'مجلس الأمن', 'قرار دولي', 'تهديد سياسي', 'موقف رسمي', 'تفاهمات', 'وساطة'],
                'weight' => 0.75
            ],
            'economic' => [
                'name' => 'الطبقة الاقتصادية واللوجستية',
                'keywords' => ['اقتصاد', 'نفط', 'غاز', 'ميناء', 'تجارة', 'شحن', 'وقود', 'عملة', 'سوق', 'أسعار', 'عقوبات مالية', 'إمداد', 'سلسلة توريد', 'تصدير', 'استيراد'],
                'weight' => 0.7
            ],
            'social' => [
                'name' => 'الطبقة الاجتماعية والنفسية',
                'keywords' => ['احتجاج', 'تظاهر', 'تحريض', 'تضليل', 'دعاية', 'حرب نفسية', 'رأي عام', 'تعبئة', 'ذعر', 'إشاعة', 'معنويات', 'خطاب', 'حملة وسوم'],
                'weight' => 0.65
            ],
            'energy' => [
                'name' => 'طبقة الطاقة والمرافق',
                'keywords' => ['نفط', 'غاز', 'مصفاة', 'كهرباء', 'محطة توليد', 'شبكة طاقة', 'ماء', 'سد', 'خط أنابيب', 'طاقة', 'وقود', 'مرافق حيوية'],
                'weight' => 0.9
            ],
            'strategic_passages' => [
                'name' => 'طبقة الممرات الاستراتيجية',
                'keywords' => ['مضيق', 'هرمز', 'باب المندب', 'قناة السويس', 'ممر بحري', 'قاعدة', 'مجال جوي', 'ملاحة', 'طريق تجاري', 'نقطة اختناق', 'مياه إقليمية', 'نفوذ'],
                'weight' => 0.95
            ]
        ];
    }
    
    /**
     * تحليل حدث باستخدام طبقات الحرب المركبة
     * 
     * @param array $event_data بيانات الحدث
     * @return array نتائج التحليل
     */
    public function analyzeLayers(array $event_data): array {
        $title = $event_data['title'] ?? '';
        $content = $event_data['description'] ?? $event_data['content'] ?? $event_data['war_data'] ?? '';
        $full_text = $title . ' ' . $content;
        
        // استخدام الدوال الوظيفية من ملف التحديث إذا كانت موجودة
        if (function_exists('sod_classify_hybrid_layers')) {
            return sod_classify_hybrid_layers($title, $content);
        }
        
        // تحليل مدمج إذا لم تكن الدوال متاحة
        $active_layers = [];
        $layer_scores = [];
        
        foreach ($this->layers_dictionary as $layer_key => $layer_info) {
            $score = $this->calculateLayerScore($full_text, $layer_info['keywords']);
            if ($score > 0) {
                $active_layers[] = $layer_key;
                $layer_scores[$layer_key] = $score;
            }
        }
        
        $hybrid_score = count($active_layers) >= 2 ? 
            min(100, count($active_layers) * 15 + array_sum($layer_scores)) : 
            (count($active_layers) * 10);
        
        return [
            'primary_layer' => !empty($active_layers) ? reset($active_layers) : 'general',
            'active_layers' => $active_layers,
            'layer_scores' => $layer_scores,
            'active_layers_count' => count($active_layers),
            'is_hybrid' => count($active_layers) >= 2,
            'hybrid_score' => $hybrid_score,
            'layers' => array_map(function($key) {
                return $this->layers_dictionary[$key]['name'];
            }, $active_layers)
        ];
    }
    
    /**
     * حساب درجة طبقة معينة
     */
    private function calculateLayerScore(string $text, array $keywords): float {
        $matches = 0;
        $total_weight = 0;
        
        foreach ($keywords as $keyword) {
            if (mb_stripos($text, $keyword) !== false) {
                $matches++;
                $total_weight += 10;
            }
        }
        
        return min(100, $matches * 10 + $total_weight * 0.5);
    }
    
    /**
     * استخراج شبكة الفاعلين من حدث
     */
    public function extractActorNetwork(string $title, string $content = ''): array {
        if (function_exists('sod_extract_actor_network')) {
            return sod_extract_actor_network($title, $content);
        }
        
        $full_text = $title . ' ' . $content;
        $network = [
            'primary_actor' => '',
            'secondary_actors' => [],
            'sponsors' => [],
            'funders' => [],
            'media_operators' => [],
            'relationships' => []
        ];
        
        // استخراج الفاعل الرئيسي
        if (preg_match('/(?:أعلنت|أكدت|صرحت|قالت)\s+(?:[\w\s]+)(?:عن|أن|بـ)/u', $full_text, $matches)) {
            $network['primary_actor'] = trim($matches[1] ?? '');
        }
        
        return $network;
    }
    
    /**
     * حساب Scores المتقدم للحدث
     */
    public function calculateAdvancedScores(array $event_data): array {
        if (function_exists('sod_calculate_advanced_scores')) {
            return sod_calculate_advanced_scores($event_data);
        }
        
        $title = $event_data['title'] ?? '';
        $content = $event_data['description'] ?? '';
        $score = (int)($event_data['score'] ?? 0);
        
        $threat_indicators = ['اغتيال', 'تدمير', 'قتلى', 'جرحى', 'استهداف', 'هجوم', 'انفجار'];
        $escalation_indicators = ['تصعيد', 'رد', 'انتقام', 'تهديد', 'وعيد', 'حشد', 'تحشيد'];
        
        $threat_score = 0;
        foreach ($threat_indicators as $indicator) {
            if (mb_stripos($title . ' ' . $content, $indicator) !== false) {
                $threat_score += 15;
            }
        }
        
        $escalation_score = 0;
        foreach ($escalation_indicators as $indicator) {
            if (mb_stripos($title . ' ' . $content, $indicator) !== false) {
                $escalation_score += 12;
            }
        }
        
        $threat_score = min(100, $threat_score + intval($score * 0.3));
        $escalation_score = min(100, $escalation_score);
        
        $risk_level = 'منخفض';
        if ($threat_score >= 70 || $escalation_score >= 60) $risk_level = 'حرج';
        elseif ($threat_score >= 50 || $escalation_score >= 40) $risk_level = 'مرتفع';
        elseif ($threat_score >= 30 || $escalation_score >= 25) $risk_level = 'متوسط';
        
        return [
            'sentiment_score' => 0,
            'threat_score' => $threat_score,
            'escalation_score' => $escalation_score,
            'confidence_score' => 50,
            'stability_index' => 100 - $threat_score,
            'aggression_index' => $threat_score * 0.8,
            'risk_level' => $risk_level,
            'impact_radius' => $threat_score >= 60 ? 'إقليمي' : 'محلي',
            'urgency_level' => $threat_score >= 70 ? 'حرج' : ($threat_score >= 40 ? 'عالي' : 'عادي')
        ];
    }
    
    /**
     * التحقق المتقدم من الحدث
     */
    public function verifyEvent(array $event_data): array {
        if (function_exists('sod_advanced_verification')) {
            return sod_advanced_verification($event_data);
        }
        
        $sources_count = 0;
        $has_visual = false;
        $has_official = false;
        
        $source = $event_data['source_name'] ?? '';
        if (in_array($source, ['رويترز', 'أسوشيتد برس', 'فرانس برس', 'وكالة الأنباء الرسمية'])) {
            $sources_count = 2;
            $has_official = true;
        }
        
        if (!empty($event_data['image_url'])) {
            $has_visual = true;
        }
        
        $status = 'غير مؤكد';
        if ($sources_count >= 2 && $has_official) $status = 'مؤكد';
        elseif ($sources_count >= 1) $status = 'محتمل';
        
        return [
            'status' => $status,
            'sources_count' => $sources_count,
            'visual_evidence' => $has_visual,
            'satellite_imagery' => false,
            'official_statement' => $has_official,
            'source_conflict' => false,
            'notes' => []
        ];
    }
    
    /**
     * دمج التحليل الكامل في حدث
     */
    public function enhanceEvent(array $event_data): array {
        if (function_exists('sod_enhance_event_with_hybrid_analysis')) {
            return sod_enhance_event_with_hybrid_analysis($event_data);
        }
        
        $layer_analysis = $this->analyzeLayers($event_data);
        $scores = $this->calculateAdvancedScores($event_data);
        $actor_network = $this->extractActorNetwork(
            $event_data['title'] ?? '',
            $event_data['description'] ?? ''
        );
        $verification = $this->verifyEvent($event_data);
        
        return array_merge($event_data, [
            'hybrid_layers' => json_encode($layer_analysis, JSON_UNESCAPED_UNICODE),
            'osint_type' => ($layer_analysis['primary_layer'] ?? 'general') . '_event',
            'multi_domain_score' => (float)($layer_analysis['hybrid_score'] ?? 0),
            'sentiment_score' => (float)($scores['sentiment_score'] ?? 0),
            'threat_score' => (int)($scores['threat_score'] ?? 0),
            'escalation_score' => (int)($scores['escalation_score'] ?? 0),
            'confidence_score' => (int)($scores['confidence_score'] ?? 0),
            'risk_level' => $scores['risk_level'] ?? 'منخفض',
            'urgency_level' => $scores['urgency_level'] ?? 'عادي',
            'actor_network' => json_encode($actor_network, JSON_UNESCAPED_UNICODE),
            'verification_status' => $verification['status'] ?? 'غير مؤكد',
            'verified_sources_count' => (int)($verification['sources_count'] ?? 0),
            'has_visual_evidence' => $verification['visual_evidence'] ? 1 : 0,
            'alert_flag' => (($scores['threat_score'] ?? 0) >= 60 || ($scores['escalation_score'] ?? 0) >= 50) ? 1 : 0,
            'alert_priority' => $scores['urgency_level'] ?? 'عادي'
        ]);
    }
}
