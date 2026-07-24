<div class="cms-page-detail" x-on:open-page-preview.window="window.open($event.detail.url, '_blank', 'noopener')">
    <div class="admin-feedback" role="status" aria-live="polite" @if(!$feedback) hidden @endif>{{ $feedback }}</div>
    <section class="admin-panel cms-page-summary">
        <div><span class="admin-badge">{{ $this->page->archived_at ? 'Archived' : 'Active draft' }}</span><h2>{{ $this->page->title }}</h2><p>/{{ $this->page->slug }}</p></div>
        <dl class="admin-definition-grid"><div><dt>Type</dt><dd>{{ $this->page->type }}</dd></div><div><dt>Template</dt><dd>{{ $this->page->template_key }}</dd></div><div><dt>Locale</dt><dd>{{ $this->page->locale }}</dd></div><div><dt>Current revision</dt><dd>{{ $this->page->currentDraftRevision->revision_number }}</dd></div></dl>
        <div class="admin-page-actions">
            @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_EDIT) @if(!$this->page->archived_at)<a class="admin-primary-button" href="{{ route('admin.content.pages.edit',$this->page) }}">Edit draft</a>@endif @endcan
            @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_PREVIEW)<button class="admin-secondary-button" type="button" wire:click="preview">Preview current revision</button>@endcan
        </div>
    </section>
    <section class="admin-panel"><h2>Section outline</h2><ol>@foreach($this->page->currentDraftRevision->payload['sections'] as $section)<li>{{ str_replace('_',' ',ucfirst($section['type'])) }}</li>@endforeach</ol></section>
    <section class="admin-panel"><h2>Immutable revision history</h2><div class="cms-revision-list">@foreach($this->page->revisions->sortByDesc('revision_number') as $revision)<article><strong>Revision {{ $revision->revision_number }}</strong><span>{{ $revision->author->name }} - {{ $revision->created_at->timezone('Africa/Dar_es_Salaam')->format('Y-m-d H:i') }}</span><small>{{ $revision->change_summary ?: 'No change summary' }} - {{ substr($revision->checksum,0,12) }} - {{ count($revision->payload['sections']) }} sections</small></article>@endforeach</div></section>
    <section class="admin-panel"><h2>Lifecycle</h2><p>Pages are never deleted. Archive and restore do not affect the static storefront.</p><div class="admin-field"><label for="page-lifecycle-reason">Reason</label><textarea id="page-lifecycle-reason" wire:model="reason" maxlength="2000"></textarea></div>
        @if($this->page->archived_at) @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_RESTORE)<button class="admin-primary-button" wire:click="restore">Restore active draft</button>@endcan
        @else @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_ARCHIVE)<button class="admin-danger-button" wire:click="archive">Archive draft page</button>@endcan @endif
    </section>
</div>
