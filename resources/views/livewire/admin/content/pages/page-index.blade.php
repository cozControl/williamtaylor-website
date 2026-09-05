<div class="cms-pages">
    <div class="admin-page-actions">
        @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_CREATE)
            <a class="admin-primary-button" href="{{ route('admin.content.pages.create') }}">Create page</a>
        @endcan
    </div>
    <section class="admin-filter-panel" aria-label="Page filters">
        <div class="admin-field"><label for="page-search">Search pages</label><input id="page-search" wire:model.live.debounce.300ms="search"></div>
        <div class="admin-field"><label for="page-type">Page type</label><select id="page-type" wire:model.live="type"><option value="">All types</option>@foreach($types as $key=>$definition)<option value="{{ $key }}">{{ $definition['label'] }}</option>@endforeach</select></div>
        <div class="admin-field"><label for="page-state">Status</label><select id="page-state" wire:model.live="state"><option value="active">Current pages</option><option value="archived">Archived</option><option value="">All pages</option></select></div>
        <div class="admin-field"><label for="page-sort">Sort</label><select id="page-sort" wire:model.live="sort"><option value="updated">Recently updated</option><option value="created">Recently created</option><option value="title">Title</option></select></div>
        <button type="button" wire:click="clearFilters">Clear filters</button>
    </section>
    @if($this->pages->isEmpty())
        <section class="admin-empty-state"><h2>{{ $search ? 'No matching pages' : 'No pages yet' }}</h2><p>Create an informational page when its content is ready.</p></section>
    @else
        <div class="cms-page-list">
            @foreach($this->pages as $page)
                <article class="admin-panel cms-page-row">
                    <div><span class="admin-badge">{{ $page->archived_at ? 'Archived' : ($page->publicationState?->current_public_revision_id ? 'Visible' : 'Hidden') }}</span><h2>{{ $page->title }}</h2><p>/{{ $page->slug }} - {{ $types[$page->type]['label'] }}</p></div>
                    <dl><div><dt>Type</dt><dd>{{ $types[$page->type]['label'] }}</dd></div><div><dt>Status</dt><dd>{{ $page->archived_at ? 'Archived' : ($page->publicationState?->current_public_revision_id ? 'Visible' : 'Hidden') }}</dd></div><div><dt>Last updated</dt><dd>{{ $page->updated_at->timezone('Africa/Dar_es_Salaam')->format('Y-m-d H:i') }}</dd></div></dl>
                    <div class="admin-page-actions">@can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_EDIT) @if(!$page->archived_at)<a href="{{ route('admin.content.pages.edit', $page) }}">Edit</a>@endif @endcan</div>
                </article>
            @endforeach
        </div>
        <div class="admin-pagination">{{ $this->pages->links() }}</div>
    @endif
</div>
