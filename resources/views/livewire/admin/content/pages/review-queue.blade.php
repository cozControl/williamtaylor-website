<div class="cms-review-queue">
    <section class="admin-filter-panel" aria-label="Publishing workflow filters">
        <div class="admin-field"><label for="review-search">Search Pages</label><input id="review-search" wire:model.live.debounce.300ms="search"></div>
        <div class="admin-field"><label for="review-type">Page type</label><select id="review-type" wire:model.live="type"><option value="">All types</option>@foreach($types as $key=>$definition)<option value="{{ $key }}">{{ $definition['label'] }}</option>@endforeach</select></div>
        <div class="admin-field"><label for="review-state">Workflow state</label><select id="review-state" wire:model.live="state"><option value="in_review">In review</option><option value="changes_requested">Changes requested</option><option value="approved">Approved</option><option value="scheduled">Scheduled</option><option value="published">Recently published</option><option value="unpublished">Unpublished</option><option value="">All workflow items</option></select></div>
        <div class="admin-field"><label for="review-sort">Sort</label><select id="review-sort" wire:model.live="sort"><option value="transition">Last transition</option><option value="schedule">Schedule</option><option value="title">Title</option></select></div>
    </section>
    @if($this->items->isEmpty())
        <section class="admin-empty-state"><h2>No workflow items</h2><p>Submitted immutable revisions will appear here. Public storefront projection is not active in this phase.</p></section>
    @else
        <div class="cms-page-list">
            @foreach($this->items as $item)
                <article class="admin-panel cms-page-row">
                    <div>
                        <span class="admin-badge">{{ str_replace('_', ' ', ucfirst($item->candidate_state?->value ?? ($item->current_public_revision_id ? 'published' : 'unpublished'))) }}</span>
                        <h2><a href="{{ route('admin.content.pages.show', $item->page) }}">{{ $item->page->title }}</a></h2>
                        <p>/{{ $item->page->slug }} - {{ $types[$item->page->type]['label'] }} - {{ $item->page->locale }}</p>
                    </div>
                    <dl>
                        <div><dt>Candidate</dt><dd>{{ $item->candidateRevision?->revision_number ?? 'None' }}</dd></div>
                        <div><dt>Designated published</dt><dd>{{ $item->currentPublicRevision?->revision_number ?? 'None' }}</dd></div>
                        <div><dt>Submitter</dt><dd>{{ $item->submitter?->name ?? 'None' }}</dd></div>
                        <div><dt>Schedule</dt><dd>{{ $item->scheduled_for?->timezone('Africa/Dar_es_Salaam')->format('Y-m-d H:i T') ?? 'None' }}</dd></div>
                        <div><dt>Newer draft</dt><dd>{{ $item->candidate_revision_id && $item->page->current_draft_revision_id !== $item->candidate_revision_id ? 'Yes' : 'No' }}</dd></div>
                    </dl>
                </article>
            @endforeach
        </div>
        <div class="admin-pagination">{{ $this->items->links() }}</div>
    @endif
</div>
