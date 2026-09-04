<div class="admin-media-library">
    @can(\App\Domain\Identity\Support\PermissionRegistry::MEDIA_UPLOAD)
        <section class="admin-panel admin-upload-workspace" aria-labelledby="upload-media-title" data-media-upload-queue>
            <div class="admin-section-heading"><p>Add website assets</p><h2 id="upload-media-title">Upload media</h2></div>
            @if (!$this->providerConfigured())
                <div class="admin-feedback is-error" role="alert"><strong>Cloudinary is not configured.</strong> Add isolated environment credentials before requesting upload intents. Existing media remains available.</div>
            @else
                <p>Select approved images or videos. Each file is securely verified before it becomes available across the website.</p>
                <div class="admin-upload-dropzone">
                    <label class="admin-primary-button" for="media-files">Choose media files</label>
                    <input id="media-files" data-media-files type="file" multiple accept="image/jpeg,image/png,image/webp,image/avif,video/mp4,video/webm">
                    <span>or drag approved files here</span>
                </div>
                <button type="button" class="admin-primary-button" data-media-upload-start disabled>Upload media</button>
                <div class="admin-sr-status" aria-live="polite" data-media-queue-announcer></div>
                <div class="admin-upload-queue" data-media-queue-list aria-label="Upload queue"></div>
            @endif
        </section>
    @endcan

    <section class="admin-filter-panel" aria-label="Media filters">
        <div class="admin-field"><label for="media-search">Search media</label><input id="media-search" wire:model.live.debounce.300ms="search"></div>
        <div class="admin-field"><label for="media-type">Type</label><select id="media-type" wire:model.live="type"><option value="">All types</option><option value="image">Images</option><option value="video">Videos</option></select></div>
        <div class="admin-field"><label for="media-state">State</label><select id="media-state" wire:model.live="state"><option value="">All states</option>@foreach(['ready','processing','failed','archived'] as $value)<option value="{{ $value }}">{{ ucfirst($value) }}</option>@endforeach</select></div>
        <div class="admin-field"><label for="media-usage">Usage</label><select id="media-usage" wire:model.live="usage"><option value="">All assets</option><option value="used">In use</option><option value="unused">Not in use</option></select></div>
        <div class="admin-field"><label for="media-sort">Sort</label><select id="media-sort" wire:model.live="sort">@foreach(['newest'=>'Newest','oldest'=>'Oldest','title'=>'Title','size'=>'File size'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
    </section>
    @if($this->assets->isEmpty())
        <section class="admin-empty-state"><h2>{{ $search || $type || $state ? 'No matching media' : 'The media library is empty' }}</h2><p>Verified uploads will appear here.</p></section>
    @else
        <div class="admin-media-grid">@foreach($this->assets as $asset)<article class="admin-media-card"><div class="admin-media-preview">@if($asset->resource_type->value === 'image' && $asset->state->value === 'ready')<img src="{{ $this->thumbnailUrl($asset) }}" alt="" width="480" height="360" loading="lazy">@else<span>{{ $asset->state->value === 'failed' ? 'Upload needs attention' : strtoupper($asset->resource_type->value) }}</span>@endif</div><div><span class="admin-badge">{{ $asset->state->value === 'failed' ? 'Needs attention' : ucfirst($asset->state->value) }}</span><h2><a href="{{ route('admin.media.show',$asset) }}">{{ $asset->internal_title }}</a></h2><p>{{ $asset->original_filename }}</p><dl class="admin-media-facts"><div><dt>Dimensions</dt><dd>{{ $asset->width && $asset->height ? $asset->width.' × '.$asset->height : '—' }}</dd></div><div><dt>Type</dt><dd>{{ strtoupper($asset->format) }}</dd></div><div><dt>Size</dt><dd>{{ number_format($asset->bytes/1024,1) }} KB</dd></div><div><dt>Alt text</dt><dd>{{ $asset->is_decorative ? 'Decorative' : (filled($asset->default_alt_text) ? 'Added' : 'Missing') }}</dd></div><div><dt>Usage</dt><dd>{{ $asset->usages_count }}</dd></div></dl><a class="admin-row-link" href="{{ route('admin.media.show',$asset) }}">View and edit</a></div></article>@endforeach</div>
        <div class="admin-pagination">{{ $this->assets->links() }}</div>
    @endif
</div>
