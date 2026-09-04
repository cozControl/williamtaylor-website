@props(['label', 'model', 'assets', 'current' => null])
<fieldset class="admin-media-picker" x-data="{ mediaSearch: '' }">
    <legend>{{ $label }}</legend>
    <label class="cms-field"><span>Search ready images</span><input type="search" x-model="mediaSearch" placeholder="Search filename or alt text"></label>
    @if($assets->isEmpty())
        <div class="admin-media-picker-empty"><p>No ready images yet. Upload an image in Media Library first.</p>@can(\App\Domain\Identity\Support\PermissionRegistry::MEDIA_VIEW)<a class="admin-secondary-button" href="{{ route('admin.media.index') }}">Open Media Library</a>@endcan</div>
    @else
        <div class="admin-media-picker-grid" data-media-selector-options>
            <label class="admin-media-picker-card"><input type="radio" wire:model="{{ $model }}" value=""><span class="admin-media-picker-placeholder">No image</span><strong>Use static fallback</strong></label>
            @foreach ($assets as $asset)
                <label class="admin-media-picker-card" x-show="@js(strtolower($asset['filename'].' '.$asset['alt'])).includes(mediaSearch.toLowerCase())"><input type="radio" wire:model="{{ $model }}" value="{{ $asset['id'] }}"><img src="{{ $asset['thumbnail'] }}" alt="" width="160" height="120" loading="lazy"><strong>{{ $asset['filename'] }}</strong><span>{{ $asset['width'] }} × {{ $asset['height'] }} · {{ filled($asset['alt']) ? 'Alt text added' : 'Alt text missing' }}</span>@if ($current === $asset['id'])<span class="admin-status">Current selection</span>@endif</label>
            @endforeach
        </div>
    @endif
</fieldset>
