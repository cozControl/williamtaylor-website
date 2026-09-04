const allowed = new Map([
    ['image/jpeg', { type: 'image', max: 20 * 1024 * 1024 }],
    ['image/png', { type: 'image', max: 20 * 1024 * 1024 }],
    ['image/webp', { type: 'image', max: 20 * 1024 * 1024 }],
    ['image/avif', { type: 'image', max: 20 * 1024 * 1024 }],
    ['video/mp4', { type: 'video', max: 250 * 1024 * 1024 }],
    ['video/webm', { type: 'video', max: 250 * 1024 * 1024 }],
]);
const activeStates = new Set(['requesting_intent', 'uploading', 'uploaded', 'confirming']);
const terminalStates = new Set(['completed', 'cancelled']);

function initializeUploadQueue(root) {
    if (root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const input = root.querySelector('[data-media-files]');
    const list = root.querySelector('[data-media-queue-list]');
    const announce = root.querySelector('[data-media-queue-announcer]');
    const uploadButton = root.querySelector('[data-media-upload-start]');
    const items = new Map();
    const files = new Map();
    let sequence = 0;
    const wireId = root.closest('[wire\\:id]')?.getAttribute('wire:id');
    const wire = () => window.Livewire.find(wireId);

    function state(item, next, error = '') {
        item.state = next;
        item.error = error;
        if (next !== 'uploading') item.canCancel = activeStates.has(next);
        if (next === 'completed' || next === 'failed') announce.textContent = `${item.name}: ${next === 'completed' ? 'Ready' : error}`;
        render();
    }

    function addFiles(selected) {
        for (const file of selected) {
            const signature = `${file.name}|${file.size}|${file.lastModified}`;
            if ([...items.values()].some((item) => item.signature === signature && item.state !== 'cancelled')) continue;
            const id = `media-${Date.now()}-${++sequence}`;
            const policy = allowed.get(file.type);
            const item = { id, signature, name: file.name, mime: file.type, bytes: file.size, resourceType: policy?.type ?? '', progress: 0, state: 'selected', error: '', retries: 0, canCancel: false, result: null, duplicate: null };
            items.set(id, item);
            files.set(id, file);
            if (!policy) state(item, 'failed', 'This file type is not allowed.');
            else if (file.size < 1 || file.size > policy.max) state(item, 'failed', 'This file exceeds the approved size limit.');
            else state(item, 'ready');
        }
        input.value = '';
        render();
    }

    async function upload(item) {
        if (!['ready', 'failed', 'cancelled'].includes(item.state) || item.retries > 3) return;
        const file = files.get(item.id);
        if (!file) return state(item, 'failed', 'Reselect this file before retrying.');
        item.error = ''; item.progress = 0; item.result = null; item.duplicate = null;
        state(item, 'requesting_intent');
        try {
            const intent = await wire().call('requestUploadIntent', item.resourceType, item.mime, item.bytes);
            if (item.state === 'cancelled') return;
            item.intent = intent;
            if (intent.endpoint === 'evidence://direct-upload') return simulateUpload(item, file, intent);
            await directUpload(item, file, intent);
        } catch (error) {
            if (item.state !== 'cancelled') state(item, 'failed', safeError(error, 'Upload intent could not be created.'));
        }
    }

    async function directUpload(item, file, intent) {
        state(item, 'uploading');
        const xhr = new XMLHttpRequest();
        item.abort = () => xhr.abort();
        const body = new FormData();
        Object.entries(intent.parameters).forEach(([key, value]) => body.append(key, String(value)));
        body.append('file', file);
        xhr.timeout = 120000;
        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable && item.state === 'uploading') {
                item.progress = Math.min(95, Math.round((event.loaded / event.total) * 95));
                render();
            }
        };
        const response = await new Promise((resolve, reject) => {
            xhr.onload = () => xhr.status >= 200 && xhr.status < 300 ? resolve(xhr.responseText) : reject(new Error('Cloudinary rejected the upload.'));
            xhr.onerror = () => reject(new Error('The network upload failed.'));
            xhr.ontimeout = () => reject(new Error('The upload timed out.'));
            xhr.onabort = () => reject(new DOMException('Cancelled', 'AbortError'));
            xhr.open('POST', intent.endpoint);
            xhr.send(body);
        });
        if (item.state === 'cancelled') return;
        item.result = JSON.parse(response);
        item.result.mime_type ||= item.mime;
        state(item, 'uploaded');
        await confirm(item);
    }

    async function simulateUpload(item, file, intent) {
        state(item, 'uploading');
        for (const progress of [18, 42, 68, 92]) {
            await new Promise((resolve) => setTimeout(resolve, 80));
            if (item.state === 'cancelled') return;
            item.progress = progress; render();
        }
        item.result = {
            ...intent.parameters, asset_id: `evidence-${item.id}`, version: 1,
            format: file.name.split('.').pop().toLowerCase(), mime_type: file.type,
            original_filename: file.name, bytes: file.size, width: 1200, height: 1500,
            checksum: file.name.includes('duplicate') ? 'controlled-duplicate' : `checksum-${item.id}`,
            simulate_failure: file.name.includes('fail-confirmation'),
        };
        state(item, 'uploaded');
        await confirm(item);
    }

    async function confirm(item, override = false, reason = null) {
        state(item, 'confirming');
        try {
            const result = await wire().call('confirmUpload', {
                intentReference: item.intent?.reference || '',
                providerEvidence: item.result,
                title: item.name.replace(/\.[^.]+$/, ''),
                altText: null,
                overrideDuplicate: override,
                overrideReason: reason,
            });
            if (item.state === 'cancelled') return;
            if (result.status === 'duplicate_detected') {
                item.duplicate = result.candidate;
                return state(item, 'duplicate_detected');
            }
            if (result.status === 'failed') {
                return state(item, 'failed', result.message || 'Secure provider confirmation failed.');
            }
            item.assetId = result.assetId; item.url = result.url; item.progress = result.status === 'completed' ? 100 : 98;
            state(item, result.status);
        } catch (error) {
            if (item.state !== 'cancelled') state(item, 'failed', safeError(error, 'Secure confirmation failed. Retry confirmation while the provider response remains valid.'));
        }
    }

    function cancel(item) {
        if (!activeStates.has(item.state)) return;
        item.abort?.(); item.intent = null; item.result = null; item.progress = 0;
        state(item, 'cancelled');
    }

    function retry(item) {
        if (!['failed', 'cancelled'].includes(item.state) || item.retries >= 3) return;
        item.retries += 1;
        upload(item);
    }

    function render() {
        list.innerHTML = '';
        for (const item of items.values()) {
            const article = document.createElement('article');
            article.className = 'admin-upload-item';
            article.dataset.queueState = item.state;
            article.setAttribute('aria-labelledby', `${item.id}-name`);
            article.innerHTML = `<div><h3 id="${item.id}-name"></h3><p class="admin-upload-facts"></p></div><div class="admin-upload-status"><span class="admin-badge"></span><progress max="100"></progress><span class="admin-progress-text"></span></div><p class="admin-upload-error" role="alert"></p><div class="admin-upload-actions"></div><div class="admin-duplicate-panel" hidden></div>`;
            article.querySelector('h3').textContent = item.name;
            article.querySelector('.admin-upload-facts').textContent = `${item.mime || 'Unknown type'} - ${(item.bytes / 1024 / 1024).toFixed(2)} MB`;
            article.querySelector('.admin-badge').textContent = item.state === 'failed' ? 'Needs attention' : item.state.replaceAll('_', ' ');
            const progress = article.querySelector('progress'); progress.value = item.progress; progress.hidden = !['uploading', 'confirming', 'processing', 'completed'].includes(item.state);
            article.querySelector('.admin-progress-text').textContent = progress.hidden ? '' : item.state === 'uploading' ? `Uploading: ${item.progress}%` : item.state === 'confirming' ? 'Confirming securely' : item.state === 'processing' ? 'Processing' : 'Ready';
            const error = article.querySelector('.admin-upload-error'); error.textContent = item.error; error.hidden = !item.error;
            const actions = article.querySelector('.admin-upload-actions');
            if (activeStates.has(item.state)) actions.append(button(`Cancel ${item.name}`, () => cancel(item), 'admin-secondary-button'));
            if (['failed', 'cancelled'].includes(item.state) && item.retries < 3) actions.append(button(`Try upload again`, () => retry(item), 'admin-primary-button'));
            if (item.url) { const link = document.createElement('a'); link.href = item.url; link.textContent = 'Inspect media'; link.className = 'admin-row-link'; actions.append(link); }
            if (item.duplicate) renderDuplicate(article.querySelector('.admin-duplicate-panel'), item);
            list.append(article);
        }
        uploadButton.disabled = ![...items.values()].some((item) => item.state === 'ready');
        root.dataset.hasActiveUpload = [...items.values()].some((item) => activeStates.has(item.state)) ? 'true' : 'false';
    }

    function renderDuplicate(panel, item) {
        panel.hidden = false;
        panel.innerHTML = '<h4>Exact duplicate detected</h4><p class="duplicate-facts"></p><a class="admin-row-link">Inspect existing media</a><div class="admin-field"><label>Reason to create a separate logical asset</label><textarea maxlength="2000"></textarea></div><p class="admin-upload-error" role="alert" hidden></p><div class="admin-upload-actions"></div>';
        panel.querySelector('.duplicate-facts').textContent = `${item.duplicate.title} - ${item.duplicate.resourceType} - ${item.duplicate.width ?? 'Unknown'} x ${item.duplicate.height ?? 'Unknown'} - ${item.duplicate.usageCount} usages`;
        panel.querySelector('a').href = item.duplicate.url;
        const actions = panel.querySelector('.admin-upload-actions');
        actions.append(button('Reuse existing asset', async () => {
            const result = await wire().call('reuseDuplicate', item.duplicate.id, item.intent?.reference || '');
            item.assetId = result.assetId; item.url = result.url; item.duplicate = null; item.progress = 100; state(item, 'completed');
        }, 'admin-primary-button'));
        actions.append(button('Create separate logical asset', async () => {
            const reason = panel.querySelector('textarea').value.trim();
            if (!reason) { const error = panel.querySelector('.admin-upload-error'); error.hidden = false; error.textContent = 'A reason is required.'; error.focus(); return; }
            await confirm(item, true, reason);
        }, 'admin-danger-button'));
    }

    function button(label, handler, className) {
        const element = document.createElement('button'); element.type = 'button'; element.className = className; element.textContent = label; element.addEventListener('click', handler); return element;
    }

    function safeError(error, fallback) {
        const message = error?.message ?? '';
        return /permission|policy|signature|secret|stack|SQL/i.test(message) ? fallback : (message || fallback);
    }

    input.addEventListener('change', () => addFiles(input.files));
    root.addEventListener('dragover', (event) => { event.preventDefault(); root.classList.add('is-dragging'); });
    root.addEventListener('dragleave', () => root.classList.remove('is-dragging'));
    root.addEventListener('drop', (event) => { event.preventDefault(); root.classList.remove('is-dragging'); addFiles(event.dataTransfer.files); });
    uploadButton.addEventListener('click', () => [...items.values()].filter((item) => item.state === 'ready').forEach(upload));
    window.addEventListener('beforeunload', (event) => { if ([...items.values()].some((item) => activeStates.has(item.state))) { event.preventDefault(); event.returnValue = ''; } });
    window.__mediaUploadEvidence = { items, addFiles, state, render };
    render();
}

function bootQueues() {
    document.querySelectorAll('[data-media-upload-queue]').forEach(initializeUploadQueue);
}
document.addEventListener('livewire:init', bootQueues);
document.addEventListener('livewire:navigated', bootQueues);
if (document.readyState !== 'loading') bootQueues(); else document.addEventListener('DOMContentLoaded', bootQueues);
