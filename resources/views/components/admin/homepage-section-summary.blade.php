@props([
    'position',
    'title',
    'summary',
    'status',
    'tone' => 'default',
    'href',
    'actionLabel',
    'sectionKey',
])
@php
    $sectionVisible = app(\App\Domain\Homepage\Support\HomepageSectionVisibility::class)->isVisible($sectionKey);
@endphp

<article class="admin-panel homepage-workspace-section" data-homepage-section="{{ $position }}">
    <span class="homepage-workspace-section-number" aria-hidden="true">{{ str_pad((string) $position, 2, '0', STR_PAD_LEFT) }}</span>
    <div class="homepage-workspace-section-content">
        <p>Section {{ $position }}</p>
        <h2>{{ $title }}</h2>
        <span>{{ $summary }}</span>
    </div>
    <div class="homepage-workspace-section-actions">
        <div class="homepage-workspace-visibility-badges">
        <span class="homepage-workspace-status is-{{ $sectionVisible ? 'configured' : 'default' }}" data-section-visibility="{{ $sectionVisible ? 'visible' : 'hidden' }}">{{ $sectionVisible ? 'Visible' : 'Hidden' }}</span>
        <span class="homepage-workspace-status is-{{ $tone }}">{{ $status }}</span>
        </div>
        @if(!$sectionVisible)<small class="admin-field-help">Configuration preserved</small>@endif
        @can(\App\Domain\Identity\Support\PermissionRegistry::SETTINGS_MANAGE)
            <form method="POST" action="{{ route('admin.homepage.visibility.update', $sectionKey) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="is_visible" value="{{ $sectionVisible ? '0' : '1' }}">
                <button type="submit" class="admin-visibility-toggle" role="switch" aria-checked="{{ $sectionVisible ? 'true' : 'false' }}" aria-label="{{ $title }} visibility">
                    <span>Visibility</span>
                    <span class="admin-visibility-toggle-track" aria-hidden="true"><span class="admin-visibility-toggle-thumb"></span></span>
                    <span class="admin-visibility-toggle-value" aria-hidden="true">{{ $sectionVisible ? 'On' : 'Off' }}</span>
                </button>
            </form>
        @endcan
        <a class="admin-secondary-button" href="{{ $href }}">{{ $actionLabel }}</a>
    </div>
</article>
