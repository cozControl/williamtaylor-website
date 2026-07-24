<div class="cms-pages">
    <div class="admin-page-actions">
        @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_CREATE)
            <a class="admin-primary-button" href="{{ route('admin.content.pages.create') }}">Create page</a>
        @endcan
    </div>
    <section class="admin-filter-panel" aria-label="Page filters">
        <div class="admin-field"><label for="page-search">Search pages</label><input id="page-search" wire:model.live.debounce.300ms="search"></div>
        <div class="admin-field"><label for="page-type">Page type</label><select id="page-type" wire:model.live="type"><option value="">All types</option>@foreach($types as $key=>$definition)<option value="{{ $key }}">{{ $definition['label'] }}</option>@endforeach</select></div>
        <div class="admin-field"><label for="page-state">Draft state</label><select id="page-state" wire:model.live="state"><option value="active">Active drafts</option><option value="archived">Archived</option><option value="">All drafts</option></select></div>
        <div class="admin-field"><label for="page-sort">Sort</label><select id="page-sort" wire:model.live="sort"><option value="updated">Recently updated</option><option value="created">Recently created</option><option value="title">Title</option></select></div>
    </section>
    @if($this->pages->isEmpty())
        <section class="admin-empty-state"><h2>{{ $search ? 'No matching draft pages' : 'No draft pages yet' }}</h2><p>Create a typed page when client-approved content is ready. Existing storefront routes remain static.</p></section>
    @else
        <div class="cms-page-list">
            @foreach($this->pages as $page)
                <article class="admin-panel cms-page-row">
                    <div><span class="admin-badge">{{ $page->archived_at ? 'Archived' : 'Active draft' }}</span><h2><a href="{{ route('admin.content.pages.show',$page) }}">{{ $page->title }}</a></h2><p>/{{ $page->slug }} - {{ $types[$page->type]['label'] }} - {{ $page->locale }}</p></div>
                    <dl><div><dt>Revision</dt><dd>{{ $page->currentDraftRevision?->revision_number }}</dd></div><div><dt>Last editor</dt><dd>{{ $page->updater->name }}</dd></div><div><dt>Template</dt><dd>{{ str_replace('_',' ',$page->template_key) }}</dd></div></dl>
                </article>
            @endforeach
        </div>
        <div class="admin-pagination">{{ $this->pages->links() }}</div>
    @endif
</div>
