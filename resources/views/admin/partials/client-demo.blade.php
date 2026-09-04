@php
    $managedPages = \App\Domain\Content\Models\Page::query()
        ->with(['currentDraftRevision', 'updater:id,name', 'publicationState'])
        ->orderByDesc('updated_at')
        ->get();
    $readiness = app(\App\Domain\Publishing\Support\PublicationReadiness::class);
    $blockedPages = $managedPages->filter(fn ($page) => !$page->currentDraftRevision || $readiness->inspect($page, $page->currentDraftRevision)['blockers'] !== []);
    $mediaIssuePages = $blockedPages->filter(fn ($page) => $page->currentDraftRevision && collect($readiness->inspect($page, $page->currentDraftRevision)['blockers'])->contains(fn ($message) => str_contains(strtolower($message), 'media') || str_contains(strtolower($message), 'alt text')));
    $lastPage = $managedPages->first();
    $lastPublication = $managedPages->filter(fn ($page) => $page->publicationState?->current_public_revision_id)->sortByDesc(fn ($page) => $page->publicationState?->last_transition_at)->first();
@endphp

@can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_VIEW)
    <section class="admin-panel" aria-labelledby="website-content-title">
        <div class="admin-section-heading"><p>Website Content</p><h2 id="website-content-title">Manage Website Content</h2></div>
        <p><strong>Homepage and global content</strong> controls supported announcement, navigation, brand/profile, contact, social, footer, and global Media fields. The protected imported homepage body remains static; this is not a drag-and-drop page builder.</p>
        <p class="admin-notice">Page SEO management is not available in this demo checkpoint.</p>
        <dl class="admin-metric-grid">
            <div><dt>Managed Pages</dt><dd>{{ $managedPages->count() }}</dd></div>
            <div><dt>Active drafts</dt><dd>{{ $managedPages->whereNull('archived_at')->whereNotNull('current_draft_revision_id')->count() }}</dd></div>
            <div><dt>Awaiting review</dt><dd>{{ $managedPages->filter(fn ($page) => $page->publicationState?->candidate_state === \App\Domain\Publishing\Enums\CandidateState::InReview)->count() }}</dd></div>
            <div><dt>Approved, awaiting publication</dt><dd>{{ $managedPages->filter(fn ($page) => $page->publicationState?->candidate_state === \App\Domain\Publishing\Enums\CandidateState::Approved && !$page->publicationState?->current_public_revision_id)->count() }}</dd></div>
            <div><dt>Published pages</dt><dd>{{ $managedPages->filter(fn ($page) => $page->publicationState?->current_public_revision_id)->count() }}</dd></div>
            <div><dt>Readiness failures</dt><dd>{{ $blockedPages->count() }}</dd></div>
            <div><dt>Media/accessibility issues</dt><dd>{{ $mediaIssuePages->count() }}</dd></div>
            <div><dt>Environment</dt><dd>{{ app()->environment() === 'production' ? 'Production' : 'Testing' }}</dd></div>
            <div><dt>Website publishing</dt><dd>{{ config('publication_rollout.global_enabled') ? 'Available' : 'Paused' }}</dd></div>
            <div><dt>Last Page editor</dt><dd>{{ $lastPage?->updater?->name ?? 'None' }}</dd></div>
            <div><dt>Last Page update</dt><dd>{{ $lastPage?->updated_at?->timezone('Africa/Dar_es_Salaam')->format('Y-m-d H:i') ?? 'None' }}</dd></div>
            <div><dt>Last Page publication</dt><dd>{{ $lastPublication?->publicationState?->last_transition_at?->timezone('Africa/Dar_es_Salaam')->format('Y-m-d H:i') ?? 'None' }}</dd></div>
        </dl>
        <div class="admin-page-actions">
            @can(\App\Domain\Identity\Support\PermissionRegistry::SETTINGS_VIEW)<a class="admin-primary-button" href="{{ route('admin.settings.index') }}">Homepage and global content</a>@endcan
            <a href="{{ route('admin.content.pages.index') }}">Pages</a>
            @can(\App\Domain\Identity\Support\PermissionRegistry::MEDIA_VIEW)<a href="{{ route('admin.media.index') }}">Media</a>@endcan
            <a href="{{ url('/about') }}">About</a>
            @if($lastPage)<a href="{{ route('admin.content.pages.show', $lastPage) }}">Publication status</a>@endif
        </div>
    </section>
@endcan

@can(\App\Domain\Identity\Support\PermissionRegistry::ORDERS_VIEW)
    <section class="admin-panel" aria-labelledby="customer-orders-title">
        <div class="admin-section-heading"><p>Customer Orders</p><h2 id="customer-orders-title">Manage Customer Orders</h2></div>
        <p>Open the separate DEMO-1B Order Operations workspace. Page content actions do not alter Orders.</p>
        <a class="admin-primary-button" href="{{ route('admin.orders.index') }}">Open Customer Orders</a>
    </section>
@endcan
