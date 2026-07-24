<div class="admin-media-library">
    @can(\App\Domain\Identity\Support\PermissionRegistry::MEDIA_UPLOAD)
        <section class="admin-panel admin-upload-workspace" aria-labelledby="upload-media-title" data-media-upload-queue>
            <div class="admin-section-heading"><p>Direct provider upload</p><h2 id="upload-media-title">Upload media</h2></div>
            @if (!$this->providerConfigured())
                <div class="admin-feedback is-error" role="alert"><strong>Cloudinary is not configured.</strong> Add isolated environment credentials before requesting upload intents. Existing media remains available.</div>
            @else
                <p>Select images or videos. Files upload directly to Cloudinary, then Laravel verifies each result before acceptance. Active local file references cannot survive browser closure.</p>
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
        <div class="admin-field"><label for="media-sort">Sort</label><select id="media-sort" wire:model.live="sort">@foreach(['newest'=>'Newest','oldest'=>'Oldest','title'=>'Title','size'=>'File size'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
    </section>
    @if($this->assets->isEmpty())
        <section class="admin-empty-state"><h2>{{ $search || $type || $state ? 'No matching media' : 'The media library is empty' }}</h2><p>Verified uploads will appear here.</p></section>
    @else
        <div class="admin-media-grid">@foreach($this->assets as $asset)<article class="admin-media-card"><div class="admin-media-preview"><span>{{ strtoupper($asset->resource_type->value) }}</span></div><div><span class="admin-badge">{{ $asset->state->value }}</span><h2><a href="{{ route('admin.media.show',$asset) }}">{{ $asset->internal_title }}</a></h2><p>{{ $asset->original_filename }}</p><small>{{ number_format($asset->bytes/1024,1) }} KB - {{ $asset->usages_count }} usages</small></div></article>@endforeach</div>
        <div class="admin-pagination">{{ $this->assets->links() }}</div>
    @endif
</div>
