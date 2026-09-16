@props([
    'id',
    'name',
    'mode' => 'single',
    'selected' => [],
    'buttonLabel' => 'Choose media',
    'changeLabel' => 'Change media',
    'showOrder' => false,
    'showAlt' => false,
    'orderName' => 'gallery_order',
    'altName' => 'media_alt',
    'orders' => [],
    'alts' => [],
    'error' => null,
    'endpoint' => null,
    'assetLabel' => 'images',
    'previewAspect' => null,
])

<div @class(['admin-picker', 'admin-field-invalid' => $error]) x-data="adminMediaPicker(@js([
    'endpoint' => $endpoint ?? route('admin.media.picker'),
    'mode' => $mode,
    'selected' => array_values($selected),
]))" data-media-picker="{{ $id }}">
    <div class="admin-picker-selection" x-show="selected.length">
        <template x-for="asset in selected" :key="asset.id">
            <article class="admin-picker-selected-card">
                <img :src="asset.thumbnail" :alt="asset.alt || ''" @if($previewAspect) style="width:128px;max-width:35%;height:auto;aspect-ratio:{{ $previewAspect }};object-fit:contain" @endif>
                <div><strong x-text="asset.title"></strong><small x-text="asset.filename"></small></div>
                <button type="button" class="admin-secondary-button" @click="remove(asset.id)">Remove</button>
                <input type="hidden" :name="mode === 'multiple' ? '{{ $name }}[]' : '{{ $name }}'" :value="asset.id">
                @if ($showOrder)
                    <div class="admin-field"><label :for="'{{ $id }}-order-'+asset.id">Display order</label><input :id="'{{ $id }}-order-'+asset.id" :name="'{{ $orderName }}['+asset.id+']'" type="number" min="0" max="999" :value="@js($orders)[asset.id] ?? ''"></div>
                @endif
                @if ($showAlt)
                    <div class="admin-field"><label :for="'{{ $id }}-alt-'+asset.id">Alt text</label><input :id="'{{ $id }}-alt-'+asset.id" :name="'{{ $altName }}['+asset.id+']'" :value="@js($alts)[asset.id] ?? asset.alt"><small class="admin-field-help">Contextual override; the Media Asset default is used when blank.</small></div>
                @endif
            </article>
        </template>
    </div>
    <p class="admin-field-help" x-show="!selected.length">No {{ rtrim($assetLabel, 's') }} selected.</p>
    <button type="button" class="admin-secondary-button" @click="open()" @if($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif><span x-text="selected.length ? @js($changeLabel) : @js($buttonLabel)">{{ count($selected) > 0 ? $changeLabel : $buttonLabel }}</span></button>
    @if($error)<small id="{{ $id }}-error" class="admin-field-error" role="alert">{{ $error }}</small>@endif

    <dialog class="admin-media-picker-dialog" x-ref="dialog" @close="cancel()">
        <div class="admin-picker-dialog-header"><div><p>Media Library</p><h2>Choose media</h2></div><button type="button" aria-label="Close media picker" @click="cancel()">×</button></div>
        <x-admin.field label="Search" for="{{ $id }}-search" help="Search filename, title or default alt text."><input id="{{ $id }}-search" type="search" x-model="search" @input.debounce.400ms="load(1)" placeholder="Filename or alt text"></x-admin.field>
        <div class="admin-picker-current" x-show="working.length"><strong>Current selection</strong><template x-for="asset in working" :key="'current-'+asset.id"><span x-text="asset.title"></span></template></div>
        <div class="admin-picker-toolbar"><span x-text="loading ? 'Loading…' : total+' ready '+@js($assetLabel)"></span><div><a class="admin-secondary-button" href="{{ route('admin.media.index') }}" target="_blank" rel="noopener">Upload new media</a><button type="button" class="admin-secondary-button" @click="load(1)">Refresh media</button></div></div>
        <div class="admin-picker-results" x-show="results.length">
            <template x-for="asset in results" :key="asset.id">
                <button type="button" class="admin-picker-result" :class="{'is-selected': isWorking(asset.id)}" @click="toggle(asset)">
                    <img :src="asset.thumbnail" alt="" loading="lazy"><strong x-text="asset.title"></strong><small x-text="asset.filename"></small><span x-text="asset.has_alt ? 'Alt text added' : 'Alt text missing'"></span>
                </button>
            </template>
        </div>
        <div class="admin-picker-empty" x-show="!loading && !results.length"><p>No ready {{ $assetLabel }} found.</p><a href="{{ route('admin.media.index') }}" target="_blank" rel="noopener">Upload media to the Media Library first.</a></div>
        <div class="admin-picker-pagination"><button type="button" class="admin-secondary-button" @click="load(page-1)" :disabled="page <= 1">Previous</button><span x-text="'Page '+page+' of '+lastPage"></span><button type="button" class="admin-secondary-button" @click="load(page+1)" :disabled="page >= lastPage">Next</button></div>
        <div class="admin-page-actions"><button type="button" class="admin-secondary-button" @click="cancel()">Cancel</button><button type="button" class="admin-primary-button" @click="commit()" :disabled="!working.length">Use selected</button></div>
    </dialog>
</div>

@once
<script>
    window.adminMediaPicker = (config) => ({
        mode: config.mode, selected: config.selected, working: [], results: [], search: '', loading: false, page: 1, lastPage: 1, total: 0,
        open() { this.working = this.selected.map((item) => ({...item})); this.$refs.dialog.showModal(); this.load(1); },
        async load(page) { this.loading = true; const url = new URL(config.endpoint, window.location.origin); url.searchParams.set('page', page); if (this.search.trim()) url.searchParams.set('search', this.search.trim()); const response = await fetch(url, {headers: {'Accept': 'application/json'}}); if (!response.ok) { this.loading = false; return; } const payload = await response.json(); this.results = payload.data; this.page = payload.current_page; this.lastPage = payload.last_page; this.total = payload.total; this.loading = false; },
        isWorking(id) { return this.working.some((item) => item.id === id); },
        toggle(asset) { if (this.mode === 'single') { this.working = [asset]; return; } this.working = this.isWorking(asset.id) ? this.working.filter((item) => item.id !== asset.id) : [...this.working, asset]; },
        commit() { this.selected = this.working.map((item) => ({...item})); this.$refs.dialog.close('commit'); },
        cancel() { if (this.$refs.dialog.open) this.$refs.dialog.close('cancel'); this.working = this.selected.map((item) => ({...item})); },
        remove(id) { this.selected = this.selected.filter((item) => item.id !== id); },
    });
</script>
@endonce
