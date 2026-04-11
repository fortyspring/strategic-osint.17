(function () {
const cfg = window.sodDbAdminConfig || {};
const ajaxUrl = cfg.ajaxurl || window.ajaxurl || '';
const nonce = cfg.nonce || '';

function post(action, extra) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('nonce', nonce);
    Object.keys(extra || {}).forEach(function (k) {
        fd.append(k, extra[k]);
    });
    return fetch(ajaxUrl, { method: 'POST', body: fd }).then(function (r) { return r.json(); });
}

function initDuplicateCleanup() {
    const runBtn = document.getElementById('so-dup-run');
    const resetBtn = document.getElementById('so-dup-reset');
    const batchEl = document.getElementById('so-dup-batch');
    if (!runBtn || !resetBtn || !batchEl) return;
    const msgEl = document.getElementById('so-dup-msg');
    const barEl = document.getElementById('so-dup-bar');
    const percentEl = document.getElementById('so-dup-percent');
    const processedEl = document.getElementById('so-dup-processed');
    const totalEl = document.getElementById('so-dup-total');
    const deletedEl = document.getElementById('so-dup-deleted');
    const statusEl = document.getElementById('so-dup-status');
    let running = false;

    function updateUI(data) {
        if (percentEl) percentEl.textContent = parseInt(data.percent || 0, 10);
        if (processedEl) processedEl.textContent = parseInt(data.processed || 0, 10);
        if (totalEl) totalEl.textContent = parseInt(data.total || 0, 10);
        if (deletedEl) deletedEl.textContent = parseInt(data.deleted_total || 0, 10);
        if (statusEl) statusEl.textContent = data.done ? 'مكتمل' : 'قيد المتابعة';
        if (barEl) barEl.style.width = parseInt(data.percent || 0, 10) + '%';
    }

    function setButtons(disabled) {
        runBtn.disabled = disabled;
        resetBtn.disabled = disabled;
    }

    function runAuto(reset) {
        if (running) return;
        running = true;
        setButtons(true);
        if (msgEl) msgEl.textContent = 'جارٍ تنظيف المكرر على دفعات متتابعة...';

        function step(firstReset) {
            post('so_ajax_duplicate_cleanup_batch', {
                batch: parseInt(batchEl.value || 50, 10),
                reset: firstReset ? 1 : 0
            }).then(function (res) {
                if (!res || !res.success) throw new Error('ajax_failed');
                updateUI(res.data || {});
                if (res.data && res.data.done) {
                    if (msgEl) msgEl.textContent = 'اكتمل تنظيف المكرر بالكامل.';
                    running = false;
                    setButtons(false);
                    return;
                }
                if (msgEl) msgEl.textContent = 'تمت معالجة دفعة جديدة... المتابعة تلقائيًا';
                setTimeout(function () { step(false); }, 250);
            }).catch(function () {
                if (msgEl) msgEl.textContent = 'توقف التنظيف بسبب خطأ مؤقت. خفّض حجم الدفعة ثم أعد المحاولة.';
                running = false;
                setButtons(false);
            });
        }

        step(reset);
    }

    runBtn.addEventListener('click', function () {
        runAuto(false);
    });

    resetBtn.addEventListener('click', function () {
        if (running) return;
        if (msgEl) msgEl.textContent = 'جارٍ تصفير المؤشر...';
        post('so_ajax_duplicate_cleanup_reset', {}).then(function (res) {
            if (!res || !res.success) throw new Error('reset_failed');
            updateUI({ percent: 0, processed: 0, total: 0, deleted_total: 0, done: 0 });
            if (statusEl) statusEl.textContent = 'متوقف';
            if (msgEl) msgEl.textContent = 'تمت إعادة البدء من الصفر.';
        }).catch(function () {
            if (msgEl) msgEl.textContent = 'تعذر تصفير المؤشر.';
        });
    });
}

function initReanalyze() {
    const runBtn = document.getElementById('so-re-run');
    const resetBtn = document.getElementById('so-re-reset');
    const batchEl = document.getElementById('so-re-batch');
    if (!runBtn || !resetBtn || !batchEl) return;
    const msgEl = document.getElementById('so-re-msg');
    const barEl = document.getElementById('so-re-bar');
    const percentEl = document.getElementById('so-re-percent');
    const processedEl = document.getElementById('so-re-processed');
    const totalEl = document.getElementById('so-re-total');
    const updatedEl = document.getElementById('so-re-updated');
    const statusEl = document.getElementById('so-re-status');
    const nextEl = document.getElementById('so-re-next');
    const batchViewEl = document.getElementById('so-re-batch-view');
    let running = false;

    function updateUI(data) {
        if (percentEl) percentEl.textContent = parseInt(data.percent || 0, 10);
        if (processedEl) processedEl.textContent = parseInt(data.processed || 0, 10);
        if (totalEl) totalEl.textContent = parseInt(data.total || 0, 10);
        if (updatedEl) updatedEl.textContent = parseInt(data.updated || 0, 10);
        if (nextEl) nextEl.textContent = parseInt(data.next_offset || 0, 10);
        if (batchViewEl) batchViewEl.textContent = parseInt(data.batch || batchEl.value || 300, 10);
        if (statusEl) statusEl.textContent = data.done ? 'مكتمل' : 'قيد المتابعة';
        if (barEl) barEl.style.width = parseInt(data.percent || 0, 10) + '%';
    }

    function setButtons(disabled) {
        runBtn.disabled = disabled;
        resetBtn.disabled = disabled;
    }

    function runAuto() {
        if (running) return;
        running = true;
        setButtons(true);
        if (msgEl) msgEl.textContent = 'جارٍ إعادة التحليل على دفعات متتابعة...';

        function step(first) {
            const payload = { batch: parseInt(batchEl.value || 50, 10) };
            if (first && parseInt((totalEl && totalEl.textContent) || 0, 10) === 0) payload.force_reset = 1;
            post('so_ajax_reanalyze_batch', payload).then(function (res) {
                if (!res || !res.success) throw new Error((res && res.data && (res.data.message || res.data.error)) || 'ajax_failed');
                updateUI(res.data || {});
                if (res.data && res.data.done) {
                    if (msgEl) msgEl.textContent = 'اكتملت إعادة التحليل الكامل.';
                    running = false;
                    setButtons(false);
                    return;
                }
                if (msgEl) msgEl.textContent = 'التحليل يعمل... ' + String((res.data && res.data.percent) || 0) + '%';
                setTimeout(function () { step(false); }, 200);
            }).catch(function (err) {
                if (msgEl) msgEl.textContent = 'فشل إعادة التحليل: ' + ((err && err.message) ? err.message : 'unknown');
                running = false;
                setButtons(false);
            });
        }

        step(true);
    }

    runBtn.addEventListener('click', function (e) {
        e.preventDefault();
        runAuto();
    });

    resetBtn.addEventListener('click', function (e) {
        e.preventDefault();
        if (running) return;
        if (msgEl) msgEl.textContent = 'جارٍ تصفير المؤشر...';
        post('so_ajax_reanalyze_reset', {}).then(function (res) {
            if (!res || !res.success) throw new Error('reset_failed');
            updateUI({ percent: 0, processed: 0, total: 0, updated: 0, next_offset: 0, done: 0, batch: parseInt(batchEl.value || 50, 10) });
            if (statusEl) statusEl.textContent = 'متوقف';
            if (msgEl) msgEl.textContent = 'تم تصفير المؤشر بنجاح.';
        }).catch(function (err) {
            if (msgEl) msgEl.textContent = 'تعذر التصفير: ' + ((err && err.message) ? err.message : 'unknown');
        });
    });
}

document.addEventListener('DOMContentLoaded', function () {
    initDuplicateCleanup();
    initReanalyze();
});
})();
