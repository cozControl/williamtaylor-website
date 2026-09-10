@props(['sectionKey'])
@php
    $sectionVisible = app(\App\Domain\Homepage\Support\HomepageSectionVisibility::class)->isVisible($sectionKey);
@endphp
<section class="admin-panel" aria-label="Section visibility" data-editor-section-visibility="{{ $sectionKey }}">
    <div class="admin-section-heading"><p>Section status</p><h2>Visibility</h2></div>
    <span class="homepage-workspace-status is-{{ $sectionVisible ? 'configured' : 'default' }}">{{ $sectionVisible ? 'Visible' : 'Hidden' }}</span>
    <p class="admin-field-help">{{ $sectionVisible ? 'This section is shown using its configured content source.' : 'This section is hidden from the storefront. Its configuration is preserved.' }} Visibility is independent of the content settings below.</p>
    <a class="admin-text-button" href="{{ route('admin.homepage.edit') }}">Manage visibility on Homepage</a>
</section>
