@props([
    'position',
    'title',
    'summary',
    'status',
    'tone' => 'default',
    'href',
    'actionLabel',
])

<article class="admin-panel homepage-workspace-section" data-homepage-section="{{ $position }}">
    <span class="homepage-workspace-section-number" aria-hidden="true">{{ str_pad((string) $position, 2, '0', STR_PAD_LEFT) }}</span>
    <div class="homepage-workspace-section-content">
        <p>Section {{ $position }}</p>
        <h2>{{ $title }}</h2>
        <span>{{ $summary }}</span>
    </div>
    <div class="homepage-workspace-section-actions">
        <span class="homepage-workspace-status is-{{ $tone }}">{{ $status }}</span>
        <a class="admin-secondary-button" href="{{ $href }}">{{ $actionLabel }}</a>
    </div>
</article>
