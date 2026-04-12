<?php
/**
 * Classifier Service - Enhanced NLP & Auto-Reclassification
 * Handles article classification with expanded dictionaries, grammar rules, and delayed re-processing.
 * 
 * @package StrategicOSINT
 * @version 2.1.0
 */

if (!defined('ABSPATH')) exit;

class SOD_Classifier_Service {
    
    use SOD_Singleton_Trait;

    // بنوك البيانات الموسعة (قواميس، أنماط صرفية، مرادفات)
    private $actors_db = [];
    private $locations_db = [];
    private $weapons_db = [];
    private $patterns_db = [];

    /**
     * تهيئة الخدمة وتحميل القواميس الموسعة
     */
    private function __construct() {
        $this->load_expanded_dictionaries();
        $this->hook_reclassification_scheduler();
    }

    /**
     * تحميل القواميس الموسعة (أسماء، أفعال، أنماط نحوية)
     */
    private function load_expanded_dictionaries() {
        // 1. قاموس الفاعلين (موسع جداً ليشمل الألقاب، الكنى، والرموز)
        $this->actors_db = [
            'israeli_enemy' => [
                'official' => 'العدو الإسرائيلي',
                'aliases' => [
                    'الكيان الصهيوني', 'الصهاينة', 'العدو الصهيوني', 
                    'إسرائيل', 'الدولة العبرية', 'تل أبيب', 'الاحتلال الإسرائيلي',
                    'الجيش الإسرائيلي', 'جيش العدو', 'قوات الاحتلال', 
                    'العدو المتصهيّن', 'بنو صهيون', 'النظام الصهيوني',
                    'IDF', 'Tsahal', 'Zionist Entity', 'Israeli Occupation Forces'
                ],
                'keywords' => ['غارة', 'قصف', 'اغتيال', 'توغّل', 'استهداف']
            ],
            'resistance' => [
                'official' => 'المقاومة الإسلامية',
                'aliases' => [
                    'حزب الله', 'المقاومة', 'الإسلاميون', 'المجاهدون',
                    'أنصار الله', 'محور المقاومة', 'الفصائل المسلحة',
                    'القوة الرادعة', 'ألوية المقاومة', 'كتائب القسام',
                    'Hezbollah', 'Axis of Resistance', 'Islamic Resistance'
                ],
                'keywords' => ['ردّ', 'قصْف', 'استهداف', 'عملية', 'تصدّي']
            ],
            'lebanese_army' => [
                'official' => 'الجيش اللبناني',
                'aliases' => ['القوات المسلحة اللبنانية', 'الجيش', 'ل.ج', 'LAF'],
                'keywords' => ['انتشار', 'تمركز', 'أوقف', 'اعتقل']
            ],
            'unifil' => [
                'official' => 'اليونيفيل',
                'aliases' => ['قوات الطوارئ الدولية', 'القوات الدولية', 'بلو بيريت'],
                'keywords' => ['دورية', 'انسحاب', 'مراقبة']
            ],
            'usa' => [
                'official' => 'الولايات المتحدة الأمريكية',
                'aliases' => ['أمريكا', 'واشنطن', 'الإدارة الأمريكية', 'البيت الأبيض', 'الأسطول الأمريكي'],
                'keywords' => ['دعم', 'غطاء', 'تحذير', 'وساطة']
            ]
        ];

        // 2. أنماط نحوية وصرفية متقدمة (Regex Patterns)
        $this->patterns_db = [
            // نمط: فعل + فاعل (مثال: قصفت إسرائيل...)
            'verb_actor' => '/(?:قام|شنّ|نفّذ|أقدم|باشر|شرع|بدأ)\s+(?:بـ)?\s*(?:قصف|غارة|هجوم|اغتيال|توغّل)\s+(?:من\s+)?(?:قبل\s+)?(ال[\w\s]+|إسرائيل|حزب الله|أمريكا)/u',
            
            // نمط: فاعل + فعل (مثال: الجيش الإسرائيلي يقصف...)
            'actor_verb' => '/(ال[\w\s]+(?:الجيش|القوات|ألوية|كتائب)|إسرائيل|حزب الله|أمريكا|واشنطن|تل أبيب)\s+(?:يقوم|يقصف|يستهدف|يغتال|يُنفّذ|شنّ)/u',
            
            // نمط: نسبة الفعل (مثال: نسب الهجوم إلى...)
            'attribution' => '/(?:نسب|أرجع|حمّل)\s+(?:المسؤولية|الهجوم)\s+(?:إلى|لـ)\s+(ال[\w\s]+|إسرائيل|حزب الله)/u',
            
            // نمط: أدوات الاستثناء والتأكيد (لتصفية الضوضاء)
            'negation' => '/(?:لم|لن|لا|غير|بدون)\s+(?:تثبت|يؤكد|يصدر)/u'
        ];

        // 3. قاموس المواقع (لتحديد السياق الجغرافي للفاعل)
        $this->locations_db = [
            'south_lebanon' => ['الجنوب', 'النبطية', 'صور', 'مرجعيون', 'حاصبيا', 'الضاحية الجنوبية'],
            'north_israel' => ['الجليل', 'صفد', 'حيفا', 'كريات شمونة', 'مستوطنات الشمال'],
            'bekaa' => ['البقاع', 'بعلبك', 'الهرمل', 'راشيا']
        ];
    }

    /**
     * ربط جدولة إعادة التصنيف التلقائي
     */
    private function hook_reclassification_scheduler() {
        // جدولة حدث لإعادة التصنيف بعد 3 دقائق من النشر
        add_action('sod_scheduled_reclassify', [$this, 'process_delayed_reclassification'], 10, 1);
        
        // عند نشر مقال جديد، نؤجل التصنيف الدقيق لمدة 3 دقائق
        add_action('transition_post_status', function($new_status, $old_status, $post) {
            if ($new_status === 'publish' && $old_status !== 'publish') {
                // جدولة الحدث بعد 180 ثانية (3 دقائق)
                wp_schedule_single_event(time() + 180, 'sod_scheduled_reclassify', [$post->ID]);
            }
        }, 10, 3);
    }

    /**
     * المعالجة المؤجلة (بعد دقائق من النشر)
     * هذه الدالة تضمن أخذ "الفاعل الحقيقي" حتى لو فشل التصنيف الأولي
     */
    public function process_delayed_reclassification($post_id) {
        // منع التنفيذ المتكرر
        if (get_post_meta($post_id, '_sod_deep_classified', true)) {
            return;
        }

        $post = get_post($post_id);
        if (!$post) return;

        // تجميع النص الكامل (عنوان + محتوى + مقتطف)
        $full_text = $post->post_title . ' ' . $post->post_content . ' ' . $post->post_excerpt;
        
        // تنفيذ التصنيف العميق
        $result = $this->analyze_text_deeply($full_text);

        // منطق التحديث: إذا كانت الثقة عالية (>85%) أو إذا كان الفاعل الحالي غير دقيق
        $current_actor = get_post_meta($post_id, 'sod_event_actor', true);
        $new_actor = $result['actor'] ?? '';
        $confidence = $result['confidence'] ?? 0;

        $should_update = false;
        $reason = '';

        // 1. إذا لم يكن هناك فاعل أصلاً
        if (empty($current_actor) && !empty($new_actor)) {
            $should_update = true;
            $reason = 'filling_empty';
        } 
        // 2. إذا كان الفاعل الحالي عاماً جداً والجديد محدد (مثلاً "عدو" -> "العدو الإسرائيلي")
        elseif ($this->is_generic_actor($current_actor) && $this->is_specific_actor($new_actor)) {
            $should_update = true;
            $reason = 'upgrading_specificity';
        }
        // 3. إذا كانت درجة الثقة عالية جداً (>90%) نتجاوز أي حماية
        elseif ($confidence >= 90) {
            $should_update = true;
            $reason = 'high_confidence_override';
        }

        if ($should_update && !empty($new_actor)) {
            update_post_meta($post_id, 'sod_event_actor', sanitize_text_field($new_actor));
            update_post_meta($post_id, 'sod_classification_confidence', intval($confidence));
            update_post_meta($post_id, 'sod_classification_reason', $reason);
            update_post_meta($post_id, '_sod_deep_classified', true); // علامة لمنع التكرار
            
            // تحديث البنوك الإحصائية
            $this->update_stats_banks($post_id, $result);
            
            error_log("SOD OSINT: Deep reclassification for Post #{$post_id}. Actor: {$new_actor} (Conf: {$confidence}%). Reason: {$reason}");
        } else {
            // حتى لو لم يحدث تغيير، نضع العلامة لإنهاء الجدولة
            update_post_meta($post_id, '_sod_deep_classified', true);
        }
    }

    /**
     * التحليل العميق للنص باستخدام القواميس الموسعة والأنماط
     */
    private function analyze_text_deeply($text) {
        $clean_text = $this->normalize_text($text);
        $scores = [];

        // 1. البحث عن تطابقات مباشرة في قاموس الفاعلين
        foreach ($this->actors_db as $actor_key => $data) {
            $score = 0;
            $matches = [];

            // فحص الاسم الرسمي
            if (mb_stripos($clean_text, $data['official']) !== false) {
                $score += 40;
                $matches[] = 'official';
            }

            // فحص الأسماء المستعارة (Aliases)
            foreach ($data['aliases'] as $alias) {
                if (mb_stripos($clean_text, $alias) !== false) {
                    $score += 25;
                    $matches[] = $alias;
                    break; // نكتفي بأول مطابقة لتجنب تضخيم النقاط
                }
            }

            // فحص الكلمات المفتاحية المرتبطة
            foreach ($data['keywords'] as $keyword) {
                if (mb_stripos($clean_text, $keyword) !== false) {
                    $score += 10;
                }
            }

            // تطبيق الأنماط النحوية (Regex) لتعزيز الدقة
            foreach ($this->patterns_db as $pattern_name => $regex) {
                if (preg_match($regex, $clean_text, $m)) {
                    $matched_string = isset($m[1]) ? $m[1] : $m[0];
                    // هل النمط يشير لهذا الفاعل؟
                    if ($this->string_belongs_to_actor($matched_string, $actor_key)) {
                        $score += 30; //Bonus للنمط النحوي الصحيح
                        $matches[] = "pattern:{$pattern_name}";
                    }
                }
            }

            if ($score > 0) {
                $scores[$actor_key] = [
                    'score' => $score,
                    'name' => $data['official'],
                    'matches' => $matches
                ];
            }
        }

        // اختيار الأعلى نقاطاً
        if (empty($scores)) {
            return ['actor' => 'غير محدد', 'confidence' => 0, 'details' => []];
        }

        usort($scores, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $winner = reset($scores);
        
        // تطبيع النتيجة إلى نسبة مئوية (سقف 100)
        $confidence = min(100, ($winner['score'] / 100) * 100); 
        
        // تعديل بسيط: إذا كانت النتيجة أقل من 20، نعتبرها غير مؤكدة
        if ($winner['score'] < 20) {
            $confidence = 0;
            $winner['name'] = 'غير محدد';
        }

        return [
            'actor' => $winner['name'],
            'confidence' => $confidence,
            'details' => $winner['matches']
        ];
    }

    /**
     * مساعدة: هل السلسلة النصية تنتمي لهذا الفاعل؟
     */
    private function string_belongs_to_actor($string, $actor_key) {
        $data = $this->actors_db[$actor_key];
        $check = mb_strtolower(trim($string));
        
        // فحص مباشر
        if (mb_strpos($check, mb_strtolower($data['official'])) !== false) return true;
        
        foreach ($data['aliases'] as $alias) {
            if (mb_strpos($check, mb_strtolower($alias)) !== false) return true;
        }
        
        return false;
    }

    /**
     * مساعدة: هل الفاعل عام جداً؟
     */
    private function is_generic_actor($actor) {
        $generics = ['غير محدد', 'طرف مجهول', 'مصدر أمني', 'جهات مجهولة', 'عدو', 'مقاومة'];
        return in_array($actor, $generics);
    }

    /**
     * مساعدة: هل الفاعل محدد ودقيق؟
     */
    private function is_specific_actor($actor) {
        foreach ($this->actors_db as $data) {
            if ($actor === $data['official']) return true;
        }
        return false;
    }

    /**
     * تنظيف وتوحيد النص العربي
     */
    private function normalize_text($text) {
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        // توحيد الألف والهمزات لتحسين البحث
        $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
        $text = str_replace(['ى'], 'ي', $text);
        $text = str_replace(['ة'], 'ه', $text);
        return trim($text);
    }

    /**
     * تحديث البنوك الإحصائية
     */
    private function update_stats_banks($post_id, $data) {
        // يمكن هنا إضافة منطق لتحديث جداول الإحصائيات العامة
        do_action('sod_stats_update', $post_id, $data);
    }
}
