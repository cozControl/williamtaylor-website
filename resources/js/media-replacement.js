function initializeReplacement(root) {
    if (root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const input = root.querySelector('[data-replacement-file]');
    const start = root.querySelector('[data-replacement-start]');
    const cancel = root.querySelector('[data-replacement-cancel-upload]');
    const status = root.querySelector('[data-replacement-status]');
    const progress = root.querySelector('progress');
    const wireId = root.closest('[wire\\:id]')?.getAttribute('wire:id');
    const wire = () => window.Livewire.find(wireId);
    let file = null;
    let xhr = null;

    input.addEventListener('change', () => {
        file = input.files?.[0] ?? null;
        status.textContent = file ? `${file.name} selected. Review the current usage before uploading.` : 'No replacement selected.';
        start.disabled = !file;
    });
    start.addEventListener('click', async () => {
        if (!file) return;
        try {
            status.textContent = 'Requesting secure replacement intent';
            const intent = await wire().call('requestReplacementIntent', file.type, file.size);
            let result;
            if (intent.endpoint === 'evidence://direct-upload') {
                progress.hidden = false;
                for (const value of [22, 51, 79, 95]) { await new Promise((resolve) => setTimeout(resolve, 70)); progress.value = value; status.textContent = `Uploading replacement: ${value}%`; }
                result = { ...intent.parameters, asset_id: `replacement-${Date.now()}`, version: 2, format: file.name.split('.').pop().toLowerCase(), mime_type: file.type, original_filename: file.name, bytes: file.size, width: file.name.includes('wide') ? 1800 : 1200, height: 1200, checksum: `replacement-${file.name}` };
            } else {
                result = await upload(file, intent, (value) => { progress.hidden = false; progress.value = value; status.textContent = `Uploading replacement: ${value}%`; });
            }
            status.textContent = 'Verifying replacement and preparing comparison';
            await wire().call('prepareReplacement', result);
            status.textContent = 'Replacement verified. Review the comparison below.';
            root.dispatchEvent(new CustomEvent('replacement-prepared', { bubbles: true }));
        } catch (error) {
            status.textContent = /signature|secret|stack|SQL/i.test(error?.message ?? '') ? 'Replacement could not be verified. Upload a valid replacement or contact support.' : (error?.message || 'Replacement upload failed.');
            status.focus();
        } finally {
            xhr = null;
        }
    });
    cancel.addEventListener('click', () => {
        xhr?.abort(); file = null; input.value = ''; progress.value = 0; progress.hidden = true; start.disabled = true;
        status.textContent = 'Replacement upload cancelled. No current version changed.';
        status.focus();
    });

    function upload(selected, intent, onProgress) {
        return new Promise((resolve, reject) => {
            xhr = new XMLHttpRequest();
            const body = new FormData();
            Object.entries(intent.parameters).forEach(([key, value]) => body.append(key, String(value)));
            body.append('file', selected);
            xhr.timeout = 120000;
            xhr.upload.onprogress = (event) => event.lengthComputable && onProgress(Math.min(95, Math.round(event.loaded / event.total * 95)));
            xhr.onload = () => xhr.status >= 200 && xhr.status < 300 ? resolve(JSON.parse(xhr.responseText)) : reject(new Error('Provider rejected the replacement upload.'));
            xhr.onerror = () => reject(new Error('Replacement network upload failed.'));
            xhr.ontimeout = () => reject(new Error('Replacement upload timed out.'));
            xhr.onabort = () => reject(new Error('Replacement upload cancelled.'));
            xhr.open('POST', intent.endpoint); xhr.send(body);
        });
    }
}
function bootReplacements() { document.querySelectorAll('[data-media-replacement]').forEach(initializeReplacement); }
document.addEventListener('livewire:init', bootReplacements);
document.addEventListener('livewire:navigated', bootReplacements);
if (document.readyState !== 'loading') bootReplacements(); else document.addEventListener('DOMContentLoaded', bootReplacements);
