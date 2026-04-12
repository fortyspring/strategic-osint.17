<?php
/**
 * خدمة سجل الأخبار
 * 
 * مسؤولة عن إدارة وتصنيف وتحليل سجل الأحداث الإخبارية
 * مع دعم التعلم الآلي والتجاوز اليدوي
 * 
 * @package Beiruttime\OSINT\Services
 */

namespace Beiruttime\OSINT\Services;

use Beiruttime\OSINT\Traits\Singleton;
use Beiruttime\OSINT\Traits\Loggable;

/**
 * فئة Newslog
 */
class Newslog {
    
    use Singleton, Loggable;
    
    /**
     * الحقول المتتبعة للتجاوز اليدوي
     * 
     * @var array
     */
    private $trackedFields = [
        'title', 'intel_type', 'tactical_level', 'region', 'actor_v2',
        'score', 'weapon_v2', 'target_v2', 'context_actor', 'intent'
    ];
    
    /**
     * تهيئة الخدمة
     */
    protected function __construct() {
        // التهيئة الأولية
    }
    
    /**
     * تحليل مصفوفة JSON
     * 
     * @param mixed $raw البيانات الخام
     * @return array مصفوفة محللة
     */
    public function parseJsonArray($raw): array {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || $raw === '') return [];
        $tmp = json_decode($raw, true);
        return is_array($tmp) ? $tmp : [];
    }
    
    /**
     * الحصول على حالة التجاوز اليدوي
     * 
     * @param array $row صف البيانات
     * @return array حالة التجاوز
     */
    public function getManualOverrideState(array $row): array {
        $wd = $this->parseJsonArray($row['war_data'] ?? '');
        $fd = $this->parseJsonArray($row['field_data'] ?? '');
        $manual = [];
        
        if (!empty($wd['manual_override']) && is_array($wd['manual_override'])) {
            $manual = $wd['manual_override'];
        } elseif (!empty($fd['manual_override']) && is_array($fd['manual_override'])) {
            $manual = $fd['manual_override'];
        }
        
        $fields = [];
        if (!empty($manual['fields']) && is_array($manual['fields'])) {
            foreach ($manual['fields'] as $f) {
                $f = trim((string)$f);
                if ($f !== '') $fields[$f] = true;
            }
        }
        
        return [
            'enabled' => !empty($manual['enabled']),
            'fields' => $fields,
            'updated_at' => (int)($manual['updated_at'] ?? 0),
            'editor' => (string)($manual['editor'] ?? '')
        ];
    }
    
    /**
     * تطبيق التجاوز اليدوي على البيانات المحللة
     * 
     * @param array $analyzed البيانات المحللة
     * @param array $row صف البيانات الأصلية
     * @return array البيانات المعدلة
     */
    public function applyManualOverrideToAnalyzed(array $analyzed, array $row): array {
        $state = $this->getManualOverrideState($row);
        
        if (empty($state['enabled']) || empty($state['fields'])) {
            return $analyzed;
        }
        
        $map = [
            'title' => 'title',
            'intel_type' => 'intel_type',
            'tactical_level' => 'tactical_level',
            'region' => 'region',
            'actor_v2' => 'actor_v2',
            'score' => 'score',
            'weapon_v2' => 'weapon_v2',
            'target_v2' => 'target_v2',
            'context_actor' => 'context_actor',
            'intent' => 'intent'
        ];
        
        foreach ($map as $src => $dst) {
            if (!isset($state['fields'][$src])) continue;
            if (array_key_exists($src, $row)) {
                $analyzed[$dst] = $row[$src];
            }
        }
        
        $wd = $this->parseJsonArray($analyzed['war_data'] ?? '');
        $wd['evaluation_mode'] = 'manual_override';
        $wd['evaluation_label'] = 'يدوي مقفل';
        $wd['manual_override'] = [
            'enabled' => true,
            'fields' => array_keys($state['fields']),
            'updated_at' => $state['updated_at'],
            'editor' => $state['editor']
        ];
        $analyzed['war_data'] = wp_json_encode($wd, JSON_UNESCAPED_UNICODE);
        
        $fd = $this->parseJsonArray($analyzed['field_data'] ?? '');
        $fd['manual_override'] = $wd['manual_override'];
        $fd['evaluation_meta'] = ['mode' => 'manual_override', 'label' => 'يدوي مقفل'];
        $analyzed['field_data'] = wp_json_encode($fd, JSON_UNESCAPED_UNICODE);
        
        return $analyzed;
    }
    
    /**
     * تحديد حالة التقييم
     * 
     * @param array $row صف البيانات
     * @param array $update بيانات التحديث
     * @param string $mode وضع التقييم
     * @return array بيانات التحديث المعدلة
     */
    public function markEvaluationState(array $row, array $update, string $mode = 'auto'): array {
        $wd = $this->parseJsonArray($row['war_data'] ?? '');
        
        if (isset($update['war_data'])) {
            $tmp = $this->parseJsonArray($update['war_data']);
            if ($tmp) $wd = array_merge($wd, $tmp);
        }
        
        $label = 'آلي';
        if ($mode === 'manual_override') $label = 'يدوي مقفل';
        elseif ($mode === 'manual_saved') $label = 'حُفظ يدويًا';
        
        $wd['evaluation_mode'] = $mode;
        $wd['evaluation_label'] = $label;
        $wd['evaluated_at'] = time();
        $update['war_data'] = wp_json_encode($wd, JSON_UNESCAPED_UNICODE);
        
        $fd = $this->parseJsonArray($row['field_data'] ?? '');
        if (isset($update['field_data'])) {
            $tmp = $this->parseJsonArray($update['field_data']);
            if ($tmp) $fd = array_merge($fd, $tmp);
        }
        $fd['evaluation_meta'] = ['mode' => $mode, 'label' => $label, 'at' => time()];
        $update['field_data'] = wp_json_encode($fd, JSON_UNESCAPED_UNICODE);
        
        return $update;
    }
    
    /**
     * التحقق مما إذا كان الصف مقفلاً يدوياً
     * 
     * @param array $row صف البيانات
     * @return bool هل هو مقفل يدوياً
     */
    public function isManualLockedRow(array $row): bool {
        $state = $this->getManualOverrideState($row);
        return !empty($state['enabled']);
    }
    
    /**
     * جمع حقول التجاوز اليدوي
     * 
     * @param array $row صف البيانات
     * @param array $incoming البيانات الواردة
     * @return array الحقول المتغيرة
     */
    public function collectManualOverrideFields(array $row, array $incoming = []): array {
        $fields = [];
        
        foreach ($this->trackedFields as $field) {
            $newVal = array_key_exists($field, $incoming) ? (string)$incoming[$field] : (string)($row[$field] ?? '');
            $oldVal = (string)($row[$field] ?? '');
            
            if ($field === 'score') {
                $newVal = (string)((int)$newVal);
                $oldVal = (string)((int)$oldVal);
            }
            
            if ($newVal !== $oldVal) {
                $fields[] = $field;
            }
        }
        
        if (empty($fields)) {
            $fields = $this->trackedFields;
        }
        
        return array_values(array_unique($fields));
    }
    
    /**
     * إرفاق حالة التجاوز اليدوي
     * 
     * @param array $row صف البيانات
     * @param array $update بيانات التحديث
     * @param array $fields الحقول
     * @param string $editor اسم المحرر
     * @return array بيانات التحديث المعدلة
     */
    public function attachManualOverrideState(array $row, array $update, array $fields, string $editor = ''): array {
        $wd = $this->parseJsonArray($row['war_data'] ?? '');
        
        if (isset($update['war_data'])) {
            $tmp = $this->parseJsonArray($update['war_data']);
            if ($tmp) $wd = array_merge($wd, $tmp);
        }
        
        $fd = $this->parseJsonArray($row['field_data'] ?? '');
        if (isset($update['field_data'])) {
            $tmp = $this->parseJsonArray($update['field_data']);
            if ($tmp) $fd = array_merge($fd, $tmp);
        }
        
        $manual = [
            'enabled' => true,
            'fields' => array_values(array_unique(array_filter(array_map('strval', $fields)))),
            'updated_at' => time(),
            'editor' => $editor !== '' ? $editor : 'admin'
        ];
        
        $wd['manual_override'] = $manual;
        $wd['evaluation_mode'] = 'manual_override';
        $wd['evaluation_label'] = 'يدوي مقفل';
        $wd['evaluated_at'] = time();
        
        $fd['manual_override'] = $manual;
        $fd['evaluation_meta'] = ['mode' => 'manual_override', 'label' => 'يدوي مقفل', 'at' => time()];
        
        $update['war_data'] = wp_json_encode($wd, JSON_UNESCAPED_UNICODE);
        $update['field_data'] = wp_json_encode($fd, JSON_UNESCAPED_UNICODE);
        
        return $update;
    }
    
    /**
     * استخراج حقول التصنيف من صف
     * 
     * @param array $row صف البيانات
     * @return array حقول التصنيف
     */
    public function extractClassificationFields(array $row): array {
        $wd = $this->parseJsonArray($row['war_data'] ?? '');
        
        return [
            'title' => (string)($row['title'] ?? ''),
            'intel_type' => (string)($row['intel_type'] ?? ($wd['intel_type'] ?? '')),
            'tactical_level' => (string)($row['tactical_level'] ?? ($wd['tactical_level'] ?? ($wd['level'] ?? ''))),
            'region' => (string)($row['region'] ?? ($wd['region'] ?? '')),
            'actor_v2' => (string)($row['actor_v2'] ?? ($wd['actor'] ?? '')),
            'target_v2' => (string)($row['target_v2'] ?? ($wd['target'] ?? '')),
            'context_actor' => (string)($row['context_actor'] ?? ($wd['context_actor'] ?? '')),
            'intent' => (string)($row['intent'] ?? ($wd['intent'] ?? '')),
            'weapon_v2' => (string)($row['weapon_v2'] ?? ($wd['weapon_means'] ?? '')),
            'score' => (int)($row['score'] ?? 0),
            'status' => (string)($row['status'] ?? 'published'),
            'war_data' => $wd,
        ];
    }
}
