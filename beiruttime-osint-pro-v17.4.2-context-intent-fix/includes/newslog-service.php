<?php
if (!defined('ABSPATH')) exit;

function sod_parse_json_array($raw): array {
    if (is_array($raw)) return $raw;
    if (!is_string($raw) || $raw === '') return [];
    $tmp = json_decode($raw, true);
    return is_array($tmp) ? $tmp : [];
}

function sod_get_manual_override_state(array $row): array {
    $wd = sod_parse_json_array($row['war_data'] ?? '');
    $fd = sod_parse_json_array($row['field_data'] ?? '');
    $manual = [];
    if (!empty($wd['manual_override']) && is_array($wd['manual_override'])) $manual = $wd['manual_override'];
    elseif (!empty($fd['manual_override']) && is_array($fd['manual_override'])) $manual = $fd['manual_override'];
    $fields = [];
    if (!empty($manual['fields']) && is_array($manual['fields'])) {
        foreach ($manual['fields'] as $f) {
            $f = trim((string)$f);
            if ($f !== '') $fields[$f] = true;
        }
    }
    return ['enabled'=>!empty($manual['enabled']),'fields'=>$fields,'updated_at'=>(int)($manual['updated_at'] ?? 0),'editor'=>(string)($manual['editor'] ?? '')];
}

function sod_apply_manual_override_to_analyzed(array $analyzed, array $row): array {
    $state = sod_get_manual_override_state($row);
    if (empty($state['enabled']) || empty($state['fields'])) return $analyzed;
    $map = ['title'=>'title','intel_type'=>'intel_type','tactical_level'=>'tactical_level','region'=>'region','actor_v2'=>'actor_v2','score'=>'score','weapon_v2'=>'weapon_v2','target_v2'=>'target_v2','context_actor'=>'context_actor','intent'=>'intent'];
    foreach ($map as $src => $dst) {
        if (!isset($state['fields'][$src])) continue;
        if (array_key_exists($src, $row)) $analyzed[$dst] = $row[$src];
    }
    $wd = sod_parse_json_array($analyzed['war_data'] ?? '');
    $wd['evaluation_mode'] = 'manual_override';
    $wd['evaluation_label'] = 'يدوي مقفل';
    $wd['manual_override'] = ['enabled'=>true,'fields'=>array_keys($state['fields']),'updated_at'=>$state['updated_at'],'editor'=>$state['editor']];
    $analyzed['war_data'] = wp_json_encode($wd, JSON_UNESCAPED_UNICODE);
    $fd = sod_parse_json_array($analyzed['field_data'] ?? '');
    $fd['manual_override'] = $wd['manual_override'];
    $fd['evaluation_meta'] = ['mode'=>'manual_override','label'=>'يدوي مقفل'];
    $analyzed['field_data'] = wp_json_encode($fd, JSON_UNESCAPED_UNICODE);
    return $analyzed;
}

function sod_mark_evaluation_state(array $row, array $update, string $mode = 'auto'): array {
    $wd = sod_parse_json_array($row['war_data'] ?? '');
    if (isset($update['war_data'])) {
        $tmp = sod_parse_json_array($update['war_data']);
        if ($tmp) $wd = array_merge($wd, $tmp);
    }
    $label = 'آلي';
    if ($mode === 'manual_override') $label = 'يدوي مقفل';
    elseif ($mode === 'manual_saved') $label = 'حُفظ يدويًا';
    $wd['evaluation_mode'] = $mode;
    $wd['evaluation_label'] = $label;
    $wd['evaluated_at'] = time();
    $update['war_data'] = wp_json_encode($wd, JSON_UNESCAPED_UNICODE);

    $fd = sod_parse_json_array($row['field_data'] ?? '');
    if (isset($update['field_data'])) {
        $tmp = sod_parse_json_array($update['field_data']);
        if ($tmp) $fd = array_merge($fd, $tmp);
    }
    $fd['evaluation_meta'] = ['mode'=>$mode,'label'=>$label,'at'=>time()];
    $update['field_data'] = wp_json_encode($fd, JSON_UNESCAPED_UNICODE);
    return $update;
}

function sod_is_manual_locked_row(array $row): bool {
    $state = sod_get_manual_override_state($row);
    return !empty($state['enabled']);
}

function sod_collect_manual_override_fields(array $row, array $incoming = []): array {
    $fields = [];
    $tracked = ['title','intel_type','tactical_level','region','actor_v2','score','weapon_v2','target_v2','context_actor','intent'];
    foreach ($tracked as $field) {
        $newVal = array_key_exists($field, $incoming) ? (string)$incoming[$field] : (string)($row[$field] ?? '');
        $oldVal = (string)($row[$field] ?? '');
        if ($field === 'score') {
            $newVal = (string)((int)$newVal);
            $oldVal = (string)((int)$oldVal);
        }
        if ($newVal !== $oldVal) $fields[] = $field;
    }
    if (empty($fields)) $fields = ['actor_v2','intel_type','tactical_level','region','score','weapon_v2','target_v2','context_actor','intent'];
    return array_values(array_unique($fields));
}

function sod_attach_manual_override_state(array $row, array $update, array $fields, string $editor = ''): array {
    $wd = sod_parse_json_array($row['war_data'] ?? '');
    if (isset($update['war_data'])) {
        $tmp = sod_parse_json_array($update['war_data']);
        if ($tmp) $wd = array_merge($wd, $tmp);
    }
    $fd = sod_parse_json_array($row['field_data'] ?? '');
    if (isset($update['field_data'])) {
        $tmp = sod_parse_json_array($update['field_data']);
        if ($tmp) $fd = array_merge($fd, $tmp);
    }
    $manual = ['enabled'=>true,'fields'=>array_values(array_unique(array_filter(array_map('strval', $fields)))),'updated_at'=>time(),'editor'=>$editor !== '' ? $editor : 'admin'];
    $wd['manual_override'] = $manual;
    $wd['evaluation_mode'] = 'manual_override';
    $wd['evaluation_label'] = 'يدوي مقفل';
    $wd['evaluated_at'] = time();
    $fd['manual_override'] = $manual;
    $fd['evaluation_meta'] = ['mode'=>'manual_override','label'=>'يدوي مقفل','at'=>time()];
    $update['war_data'] = wp_json_encode($wd, JSON_UNESCAPED_UNICODE);
    $update['field_data'] = wp_json_encode($fd, JSON_UNESCAPED_UNICODE);
    return $update;
}

function sod_newslog_extract_classification_fields(array $row): array {
    $wd = sod_parse_json_array($row['war_data'] ?? '');
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

function sod_ajax_newslog_search(): void {
    if (!current_user_can('manage_options')) { wp_send_json_error('unauthorized', 403); }
    check_ajax_referer('sod_newslog_search', 'nonce');
    global $wpdb;
    $table   = $wpdb->prefix . 'so_news_events';
    $lrn_tbl = $wpdb->prefix . 'so_manual_learning';
    $q = sanitize_text_field(wp_unslash($_POST['q'] ?? ''));
    $region = sanitize_text_field(wp_unslash($_POST['region'] ?? ''));
    $actor = sanitize_text_field(wp_unslash($_POST['actor'] ?? ''));
    $type = sanitize_text_field(wp_unslash($_POST['intel_type'] ?? ''));
    $score = (int)($_POST['score'] ?? 0);
    $page = max(1, (int)($_POST['page'] ?? 1));
    $per = min(200, max(10, (int)($_POST['per_page'] ?? 25)));
    $offset = ($page - 1) * $per;
    $where = ['1=1']; $params = [];
    if ($q) { $where[] = 'title LIKE %s'; $params[] = '%' . $wpdb->esc_like($q) . '%'; }
    if ($region) { $where[] = 'region = %s'; $params[] = $region; }
    if ($actor) { $where[] = 'actor_v2 = %s'; $params[] = $actor; }
    if ($type) { $where[] = 'intel_type = %s'; $params[] = $type; }
    if ($score > 0) { $where[] = 'score >= %d'; $params[] = $score; }
    $clause = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$clause}";
    $data_sql = "SELECT id,title,link,source_name,source_color,intel_type,tactical_level,region,actor_v2,score,status,event_timestamp,war_data,field_data,weapon_v2,target_v2,context_actor,intent,title_fingerprint FROM {$table} WHERE {$clause} ORDER BY event_timestamp DESC LIMIT %d OFFSET %d";
    $unknown_actor_values = ['','غير محدد','عام/مجهول','فاعل غير محسوم','فاعل قيد التقييم','جهة غير معلنة','فاعل سياقي','فاعل سياقي غير مباشر'];
    $total = $params ? (int)$wpdb->get_var($wpdb->prepare($count_sql, ...$params)) : (int)$wpdb->get_var($count_sql);
    $rows = $wpdb->prepare($data_sql, ...array_merge($params, [$per, $offset]));
    $items = $wpdb->get_results($rows, ARRAY_A);
    $classified_sql = "SELECT COUNT(*) FROM {$table} WHERE {$clause} AND actor_v2 NOT IN ('" . implode("','", array_map('esc_sql', $unknown_actor_values)) . "')";
    $classified_count = $params ? (int)$wpdb->get_var($wpdb->prepare($classified_sql, ...$params)) : (int)$wpdb->get_var($classified_sql);
    $unclassified_count = max(0, $total - $classified_count);
    if ($items) {
        $fps = array_filter(array_column($items, 'title_fingerprint'));
        $learned = [];
        if ($fps) {
            $ph = implode(',', array_fill(0, count($fps), '%s'));
            $lrows = $wpdb->get_col($wpdb->prepare("SELECT title_fingerprint FROM {$lrn_tbl} WHERE title_fingerprint IN ({$ph})", ...$fps));
            $learned = array_flip($lrows);
        }
        foreach ($items as &$it) {
            $it['has_learning'] = isset($learned[$it['title_fingerprint'] ?? '']);
            $normalized = sod_newslog_extract_classification_fields($it);
            $it['score'] = (int)$normalized['score'];
            $it['event_timestamp'] = (int)$it['event_timestamp'];
            $wd = (array)$normalized['war_data'];
            $manual_state = sod_get_manual_override_state(['war_data'=>wp_json_encode($wd, JSON_UNESCAPED_UNICODE), 'field_data'=>(string)($it['field_data'] ?? '')]);
            $it['has_manual_override'] = !empty($manual_state['enabled']);
            $it['evaluation_mode'] = (string)($wd['evaluation_mode'] ?? (!empty($manual_state['enabled']) ? 'manual_override' : 'auto'));
            $it['evaluation_label'] = (string)($wd['evaluation_label'] ?? (!empty($manual_state['enabled']) ? 'يدوي مقفل' : 'آلي'));
            $it['intel_type'] = (string)$normalized['intel_type'];
            $it['tactical_level'] = (string)$normalized['tactical_level'];
            $it['region'] = (string)$normalized['region'];
            $it['actor_v2'] = (string)$normalized['actor_v2'];
            if (
                empty($manual_state['enabled']) &&
                in_array($it['actor_v2'], ['', 'غير محدد', 'عام/مجهول', 'فاعل غير محسوم', 'فاعل سياقي', 'فاعل سياقي غير مباشر', 'جيش العدو الإسرائيلي'], true)
            ) {
                $it['actor_v2'] = sod_force_requested_actor_rule((string)($it['actor_v2'] ?? ''), (string)($it['region'] ?? ''), (string)($it['title'] ?? ''));
            }
            $it['context_actor'] = (string)$normalized['context_actor'];
            $it['intent'] = (string)$normalized['intent'];
            $it['target_v2'] = (string)$normalized['target_v2'];
            $it['weapon_v2'] = (string)$normalized['weapon_v2'];
        }
        unset($it);
    }
    wp_send_json_success(['items'=>$items ?? [],'total'=>$total,'page'=>$page,'per_page'=>$per,'stats'=>['classified'=>$classified_count,'unclassified'=>$unclassified_count]]);
}

function sod_ajax_newslog_save(): void {
    if (!current_user_can('manage_options')) { wp_send_json_error('unauthorized', 403); }
    check_ajax_referer('sod_newslog_save', 'nonce');
    global $wpdb;
    $table = $wpdb->prefix . 'so_news_events';
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { wp_send_json_error('invalid id'); }
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id), ARRAY_A);
    if (!$row) { wp_send_json_error('not found'); }
    $current = sod_newslog_extract_classification_fields($row);

    $new_title = sanitize_textarea_field(wp_unslash($_POST['title'] ?? $current['title']));
    $intel_type = sanitize_text_field(wp_unslash($_POST['intel_type'] ?? $current['intel_type']));
    $tac_level = sanitize_text_field(wp_unslash($_POST['tactical_level'] ?? $current['tactical_level']));
    $region = sanitize_text_field(wp_unslash($_POST['region'] ?? $current['region']));
    $actor = sanitize_text_field(wp_unslash($_POST['actor_v2'] ?? $current['actor_v2']));
    $score = min(300, max(0, (int)($_POST['score'] ?? $current['score'])));
    $status = sanitize_key($_POST['status'] ?? $current['status']);
    $weapon = sanitize_text_field(wp_unslash($_POST['weapon_v2'] ?? $current['weapon_v2']));
    $target = sanitize_text_field(wp_unslash($_POST['target_v2'] ?? $current['target_v2']));
    $context = sanitize_text_field(wp_unslash($_POST['context_actor'] ?? $current['context_actor']));
    $intent = sanitize_text_field(wp_unslash($_POST['intent'] ?? $current['intent']));
    if ($actor === '') $actor = sod_force_requested_actor_rule($actor, $region, $new_title);

    $update = ['intel_type'=>$intel_type,'tactical_level'=>$tac_level,'region'=>$region,'actor_v2'=>$actor,'score'=>$score,'status'=>$status,'weapon_v2'=>$weapon,'target_v2'=>$target,'context_actor'=>$context,'intent'=>$intent];
    if ($new_title !== $row['title']) {
        $update['title'] = $new_title;
        $update['title_fingerprint'] = so_build_title_fingerprint($new_title);
    }

    $wd = (array)$current['war_data'];
    $fd = sod_parse_json_array($row['field_data'] ?? '');
    if ($weapon) $wd['weapon_means'] = $weapon;
    if ($target) $wd['target'] = $target;
    if ($actor) $wd['actor'] = $actor;
    if ($context !== '') $wd['context_actor'] = $context;
    if ($intent !== '') $wd['intent'] = $intent;
    $wd['region'] = $region;
    $wd['intel_type'] = $intel_type;
    $wd['early_warning'] = sod_early_warning_ai($new_title, ['actor'=>$actor, 'region'=>$region, 'intel_type'=>$intel_type]);
    $wd['prediction_layer'] = sod_prediction_layer($new_title, ['actor'=>$actor, 'region'=>$region, 'intel_type'=>$intel_type, 'target'=>$target, 'weapon'=>$weapon, 'early_warning'=>$wd['early_warning']]);
    $fd['manual_saved_payload'] = ['title'=>$new_title,'actor_v2'=>$actor,'region'=>$region,'intel_type'=>$intel_type,'tactical_level'=>$tac_level,'score'=>$score,'weapon_v2'=>$weapon,'target_v2'=>$target,'context_actor'=>$context,'intent'=>$intent,'saved_at'=>time()];
    $update['war_data'] = wp_json_encode($wd, JSON_UNESCAPED_UNICODE);
    $update['field_data'] = wp_json_encode($fd, JSON_UNESCAPED_UNICODE);
    $manual_fields = sod_collect_manual_override_fields($row, array_merge($update, ['title'=>$new_title]));
    $u = wp_get_current_user();
    $editor_label = ($u && method_exists($u, 'exists') && $u->exists()) ? $u->user_login : 'admin';
    $update = sod_attach_manual_override_state($row, $update, $manual_fields, $editor_label);
    $res = sod_db_safe_update($table, $update, ['id'=>$id]);
    if (empty($res['ok'])) {
        sod_db_log_error('newslog_save', (string)($res['error'] ?? 'unknown'), ['table'=>$table,'id'=>$id,'update'=>$update]);
        wp_send_json_error('db update failed: ' . (string)($res['error'] ?? 'unknown'));
    }
    foreach ([['types',$intel_type],['levels',$tac_level],['regions',$region],['actors',$actor],['targets',$target],['contexts',$context],['intents',$intent],['weapons',$weapon]] as $pair) {
        [$bk,$val] = $pair;
        if ($val !== '') sod_add_bank_value($bk, $val);
    }
    $event_fake = ['title'=>$new_title,'source_name'=>$row['source_name'] ?? ''];
    $payload = ['actor_v2'=>$actor,'region'=>$region,'intel_type'=>$intel_type,'tactical_level'=>$tac_level,'score'=>$score,'title'=>$new_title,'target_v2'=>$target,'context_actor'=>$context,'intent'=>$intent,'weapon_v2'=>$weapon,'_early_warning'=>sod_early_warning_ai($new_title, ['actor'=>$actor,'region'=>$region,'intel_type'=>$intel_type]),'_prediction'=>sod_prediction_layer($new_title, ['actor'=>$actor,'region'=>$region,'intel_type'=>$intel_type,'target'=>$target,'weapon'=>$weapon,'early_warning'=>sod_early_warning_ai($new_title, ['actor'=>$actor,'region'=>$region,'intel_type'=>$intel_type])])];
    SO_Manual_Learning::save_feedback($event_fake, $payload);
    sod_context_memory_save_feedback($new_title, $payload);
    wp_send_json_success(['updated'=>1,'manual_lock'=>1,'training'=>['deferred'=>1]]);
}

function sod_ajax_newslog_reclassify(): void {
    if (!current_user_can('manage_options')) { wp_send_json_error('unauthorized', 403); }
    check_ajax_referer('sod_newslog_reclassify', 'nonce');
    global $wpdb;
    $table = $wpdb->prefix . 'so_news_events';
    $mode = sanitize_key($_POST['mode'] ?? 'single');
    $id = (int)($_POST['id'] ?? 0);
    $unknown_actor_values = ['','غير محدد','عام/مجهول','فاعل غير محسوم','فاعل قيد التقييم','جهة غير معلنة','فاعل سياقي','فاعل سياقي غير مباشر'];
    $count_classified = function() use ($wpdb, $table, $unknown_actor_values): int {
        return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE actor_v2 NOT IN ('" . implode("','", array_map('esc_sql', $unknown_actor_values)) . "')");
    };
    $count_total = function() use ($wpdb, $table): int { return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}"); };
    $candidate_where = "actor_v2 IN ('" . implode("','", array_map('esc_sql', $unknown_actor_values)) . "') OR (actor_v2='جيش العدو الإسرائيلي' AND title REGEXP 'ترامب|ترمب|نتنياهو|البيت الأبيض|التلفزيون الإيراني|إيرنا|الرئاسة|وفد|مفاوضات|محادثات|اجتماع|لقاء|رويترز|مصدر|تغطية|قناة|العربية|الميادين|الجزيرة|باكستان|إسلام آباد|اسلام آباد')";
    $count_candidates = function() use ($wpdb, $table, $candidate_where): int { return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$candidate_where}"); };

    $reclassify_row = function(array $row) use ($wpdb, $table) {
        $item = ['title'=>(string)($row['title'] ?? ''),'link'=>(string)($row['link'] ?? ''),'source'=>(string)($row['source_name'] ?? $row['source'] ?? ''),'color'=>(string)($row['source_color'] ?? '#1da1f2'),'date'=>(string)($row['event_timestamp'] ?? ''),'agency_loc'=>(string)($row['agency_loc'] ?? ''),'image_url'=>(string)($row['image_url'] ?? '')];
        $manual_state = sod_get_manual_override_state($row);
        if (!empty($manual_state['enabled'])) {
            $locked_actor = (string)($row['actor_v2'] ?? 'فاعل غير محسوم');
            $locked_region = (string)($row['region'] ?? 'غير محدد');
            $locked_intel = (string)($row['intel_type'] ?? 'عام');
            $locked_score = (int)($row['score'] ?? 0);
            $update_payload = sod_mark_evaluation_state($row, ['intel_type'=>$locked_intel,'tactical_level'=>(string)($row['tactical_level'] ?? ''),'region'=>$locked_region,'actor_v2'=>$locked_actor,'score'=>$locked_score,'war_data'=>(string)($row['war_data'] ?? '{}'),'field_data'=>(string)($row['field_data'] ?? '{}'),'target_v2'=>(string)($row['target_v2'] ?? ''),'context_actor'=>(string)($row['context_actor'] ?? ''),'intent'=>(string)($row['intent'] ?? ''),'weapon_v2'=>(string)($row['weapon_v2'] ?? '')], 'manual_override');
            sod_db_safe_update($table, $update_payload, ['id'=>(int)$row['id']]);
            return ['ok'=>true,'new_score'=>$locked_score,'new_actor'=>$locked_actor,'new_region'=>$locked_region,'locked'=>true];
        }
        $analyzed = SO_OSINT_Engine::process_event($item);
        if (!$analyzed || !is_array($analyzed)) return ['ok'=>false,'error'=>'classification failed'];
        $analyzed = sod_finalize_reanalysis_payload($row, $item, $analyzed);
        $wd = [];
        if (!empty($analyzed['war_data'])) { $tmp = json_decode($analyzed['war_data'], true); if (is_array($tmp)) $wd = $tmp; }
        $wd['actor'] = $analyzed['actor_v2'];
        if (!isset($wd['target']) && !empty($analyzed['target_v2'])) $wd['target'] = $analyzed['target_v2'];
        if (!isset($wd['context_actor']) && !empty($analyzed['context_actor'])) $wd['context_actor'] = $analyzed['context_actor'];
        if (!isset($wd['intent']) && !empty($analyzed['intent'])) $wd['intent'] = $analyzed['intent'];
        if (!isset($wd['early_warning'])) $wd['early_warning'] = sod_early_warning_ai((string)($item['title'] ?? ''), ['actor'=>(string)($analyzed['actor_v2'] ?? ''),'region'=>(string)($analyzed['region'] ?? ''),'intel_type'=>(string)($analyzed['intel_type'] ?? '')]);
        $analyzed['war_data'] = wp_json_encode($wd, JSON_UNESCAPED_UNICODE);
        $target_v2 = (string)($wd['target'] ?? '');
        $context_actor = (string)($wd['context_actor'] ?? '');
        $intent = (string)($wd['intent'] ?? '');
        $weapon_v2 = (string)($wd['weapon_means'] ?? '');
        $manual_state = sod_get_manual_override_state($row);
        $update_payload = ['intel_type'=>(string)($analyzed['intel_type'] ?? ''),'tactical_level'=>(string)($analyzed['tactical_level'] ?? ''),'region'=>(string)($analyzed['region'] ?? ''),'actor_v2'=>(string)($analyzed['actor_v2'] ?? ''),'score'=>(int)($analyzed['score'] ?? 0),'war_data'=>(string)($analyzed['war_data'] ?? '{}'),'field_data'=>(string)($analyzed['field_data'] ?? '{}'),'target_v2'=>$target_v2,'context_actor'=>$context_actor,'intent'=>$intent,'weapon_v2'=>$weapon_v2];
        $update_payload = sod_mark_evaluation_state($row, $update_payload, !empty($manual_state['enabled']) ? 'manual_override' : 'auto');
        $res = sod_db_safe_update($table, $update_payload, ['id'=>(int)$row['id']]);
        if (empty($res['ok'])) {
            sod_db_log_error('newslog_reclassify', (string)($res['error'] ?? 'unknown'), ['table'=>$table,'id'=>(int)$row['id'],'target_v2'=>$target_v2,'context_actor'=>$context_actor,'intent'=>$intent,'weapon_v2'=>$weapon_v2]);
            return ['ok'=>false,'error'=>'db update failed: ' . (string)($res['error'] ?? 'unknown')];
        }
        foreach ([['types',(string)($analyzed['intel_type'] ?? '')],['levels',(string)($analyzed['tactical_level'] ?? '')],['regions',(string)($analyzed['region'] ?? '')],['actors',(string)($analyzed['actor_v2'] ?? '')],['targets',$target_v2],['contexts',$context_actor],['intents',$intent],['weapons',$weapon_v2]] as $pair) {
            [$bk,$val] = $pair;
            if ($val !== '') sod_add_bank_value($bk, $val);
        }
        return ['ok'=>true,'new_score'=>(int)($analyzed['score'] ?? 0),'new_actor'=>(string)($analyzed['actor_v2'] ?? ''),'new_region'=>(string)($analyzed['region'] ?? '')];
    };

    if ($mode === 'single') {
        if (!$id) { wp_send_json_error('invalid id'); }
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id), ARRAY_A);
        if (!$row) { wp_send_json_error('not found'); }
        try {
            $result = $reclassify_row($row);
            if (empty($result['ok'])) wp_send_json_error($result['error'] ?? 'classification failed');
            wp_send_json_success(['updated'=>1,'new_score'=>$result['new_score'],'new_actor'=>$result['new_actor'],'new_region'=>$result['new_region']]);
        } catch (Throwable $e) {
            wp_send_json_error('reclassify exception: ' . $e->getMessage());
        }
    } else {
        $total_before = $count_total();
        $candidate_total = $count_candidates();
        $classified_before = $count_classified();
        $batch = min(100, max(10, (int)($_POST['batch'] ?? 50)));
        $cursor_id = max(0, (int)($_POST['cursor_id'] ?? 0));
        $updated = 0;
        $skipped = 0;
        set_time_limit(180);
        if ($candidate_total <= 0) {
            wp_send_json_success(['updated'=>0,'skipped'=>0,'done'=>1,'cursor_id'=>0,'next_cursor_id'=>0,'batch'=>$batch,'processed'=>0,'total'=>0,'percent'=>100,'stats'=>['total'=>$total_before,'classified_before'=>$classified_before,'classified_after'=>$classified_before,'unclassified_before'=>max(0, $total_before - $classified_before),'unclassified_after'=>max(0, $total_before - $classified_before)]]);
        }
        if ($cursor_id > 0) {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE ({$candidate_where}) AND id < %d ORDER BY id DESC LIMIT %d", $cursor_id, $batch), ARRAY_A);
        } else {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE {$candidate_where} ORDER BY id DESC LIMIT %d", $batch), ARRAY_A);
        }
        $last_id = 0;
        foreach ((array)$rows as $row) {
            try {
                $last_id = (int)($row['id'] ?? 0);
                $current_actor = trim((string)($row['actor_v2'] ?? ''));
                $title_text = (string)($row['title'] ?? '');
                $named_actor = sod_extract_named_nonmilitary_actor($title_text);
                $needs_refresh = in_array($current_actor, $unknown_actor_values, true) || ($current_actor === 'جيش العدو الإسرائيلي' && (sod_is_non_military_context($title_text) || $named_actor !== ''));
                if (!$needs_refresh) { $skipped++; continue; }
                $result = $reclassify_row($row);
                if (!empty($result['ok'])) $updated++;
            } catch (Throwable $e) {}
        }
        $done = empty($rows) || count((array)$rows) < $batch || $last_id <= 0;
        $next_cursor_id = $done ? 0 : $last_id;
        if ($done) {
            $processed = $candidate_total;
        } elseif ($next_cursor_id > 0) {
            $remaining = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE ({$candidate_where}) AND id < %d", $next_cursor_id));
            $processed = max(0, $candidate_total - $remaining);
        } else {
            $processed = min($candidate_total, count((array)$rows));
        }
        $total_after = $count_total();
        $classified_after = $count_classified();
        wp_send_json_success(['updated'=>$updated,'skipped'=>$skipped,'done'=>$done ? 1 : 0,'cursor_id'=>$cursor_id,'next_cursor_id'=>$next_cursor_id,'batch'=>$batch,'processed'=>$processed,'total'=>$candidate_total,'percent'=>$candidate_total > 0 ? (int)round(($processed / $candidate_total) * 100) : 100,'stats'=>['total'=>$total_after,'classified_before'=>$classified_before,'classified_after'=>$classified_after,'unclassified_before'=>max(0, $total_before - $classified_before),'unclassified_after'=>max(0, $total_after - $classified_after)]]);
    }
}

function sod_ajax_newslog_autotrain(): void {
    if (!current_user_can('manage_options')) { wp_send_json_error('unauthorized', 403); }
    check_ajax_referer('sod_newslog_save', 'nonce');
    $limit = min(5000, max(200, (int)($_POST['limit'] ?? 1200)));
    wp_send_json_success(sod_auto_dataset_training_from_newslog($limit));
}

function sod_ajax_newslog_bulk(): void {
    if (!current_user_can('manage_options')) { wp_send_json_error('unauthorized', 403); }
    check_ajax_referer('sod_newslog_bulk', 'nonce');
    global $wpdb;
    $table = $wpdb->prefix . 'so_news_events';
    $action = sanitize_text_field($_POST['bulk_action'] ?? '');
    $ids_raw = $_POST['ids'] ?? '';
    $ids = array_filter(array_map('intval', is_array($ids_raw) ? $ids_raw : explode(',', $ids_raw)));
    if (empty($ids)) { wp_send_json_error('no ids'); }
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    if ($action === 'delete') {
        $affected = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id IN ({$placeholders})", ...$ids));
        wp_send_json_success(['deleted'=>$affected]);
    } elseif (in_array($action, ['published','pending','draft'], true)) {
        $affected = $wpdb->query($wpdb->prepare("UPDATE {$table} SET status=%s WHERE id IN ({$placeholders})", $action, ...$ids));
        wp_send_json_success(['updated'=>$affected,'status'=>$action]);
    }
    wp_send_json_error('unknown action');
}

function sod_ajax_newslog_get_banks(): void {
    if (!current_user_can('manage_options')) { wp_send_json_error('unauthorized', 403); }
    check_ajax_referer('sod_newslog_search', 'nonce');
    wp_send_json_success(sod_get_visible_learning_banks());
}

function sod_ajax_newslog_add_to_bank(): void {
    if (!current_user_can('manage_options')) { wp_send_json_error('unauthorized', 403); }
    check_ajax_referer('sod_newslog_save', 'nonce');
    $bank = sanitize_text_field($_POST['bank'] ?? '');
    $value = sanitize_text_field($_POST['value'] ?? '');
    if (!$value) { wp_send_json_error('empty value'); }
    $banks = sod_add_bank_value($bank, $value);
    wp_send_json_success(['bank'=>sod_normalize_bank_key($bank),'value'=>$value,'banks'=>$banks]);
}

function sod_ajax_newslog_remove_from_bank(): void {
    if (!current_user_can('manage_options')) { wp_send_json_error('unauthorized', 403); }
    check_ajax_referer('sod_newslog_save', 'nonce');
    $bank = sanitize_text_field($_POST['bank'] ?? '');
    $value = sanitize_text_field($_POST['value'] ?? '');
    if (!$value) { wp_send_json_error('invalid'); }
    $banks = sod_remove_bank_value($bank, $value);
    wp_send_json_success(['bank'=>sod_normalize_bank_key($bank),'value'=>$value,'banks'=>$banks]);
}
