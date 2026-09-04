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
        <div class="admin-field"><label for="page-readiness">Readiness</label><select id="page-readiness" wire:model.live="readiness"><option value="">All readiness states</option><option value="ready">Ready</option><option value="blocked">Needs attention</option></select></div>
        <div class="admin-field"><label for="page-publication">Publication</label><select id="page-publication" wire:model.live="publication"><option value="">All publication states</option><option value="review">Awaiting review</option><option value="approved">Approved, awaiting publication</option><option value="published">Published to demo</option><option value="unpublished">Static fallback</option></select></div>
        <div class="admin-field"><label for="page-sort">Sort</label><select id="page-sort" wire:model.live="sort"><option value="updated">Recently updated</option><option value="created">Recently created</option><option value="title">Title</option></select></div>
        <button type="button" wire:click="clearFilters">Clear filters</button>
    </section>
    @if($this->pages->isEmpty())
        <section class="admin-empty-state"><h2>{{ $search ? 'No matching draft pages' : 'No draft pages yet' }}</h2><p>Create a typed page when client-approved content is ready. Existing storefront routes remain static.</p></section>
    @else
        <div class="cms-page-list">
            @foreach($this->pages as $page)
                @php($status = $this->status($page))
                <article class="admin-panel cms-page-row">
                    <div><span class="admin-badge">{{ $status['readiness'] }}</span><h2><a href="{{ route('admin.content.pages.show',$page) }}">{{ $page->title }}</a></h2><p>/{{ $page->slug }} - {{ $types[$page->type]['label'] }} - {{ $page->locale }}</p></div>
                    <dl><div><dt>Draft</dt><dd>{{ $status['draft'] }}</dd></div><div><dt>Review</dt><dd>{{ $status['workflow'] }}</dd></div><div><dt>Publication</dt><dd>{{ $status['publication'] }}</dd></div><div><dt>Media</dt><dd>{{ $status['media'] }}</dd></div><div><dt>Last editor</dt><dd>{{ $page->updater->name }}</dd></div><div><dt>Last modified</dt><dd>{{ $page->updated_at->timezone('Africa/Dar_es_Salaam')->format('Y-m-d H:i') }}</dd></div></dl>
                    @if($status['blockers'] !== [])<ul class="admin-errors">@foreach($status['blockers'] as $blocker)<li>{{ $blocker }}</li>@endforeach</ul>@endif
                    <div class="admin-page-actions"><a href="{{ route('admin.content.pages.show', $page) }}">Open and view history</a>@can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_EDIT) @if(!$page->archived_at)<a href="{{ route('admin.content.pages.edit', $page) }}">Edit draft</a>@endif @endcan</div>
                </article>
            @endforeach
        </div>
        <div class="admin-pagination">{{ $this->pages->links() }}</div>
    @endif
</div>
