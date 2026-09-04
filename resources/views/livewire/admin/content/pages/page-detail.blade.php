<div class="cms-page-detail" x-on:open-page-preview.window="window.open($event.detail.url, '_blank', 'noopener')">
    @php($publication = $this->page->publicationState)
    @php($candidateState = $publication?->candidate_state?->value)
    <div id="publishing-feedback" class="admin-feedback {{ $feedbackType === 'error' ? 'is-error' : '' }}" role="status" aria-live="assertive" tabindex="-1" @if(!$feedback) hidden @endif>{{ $feedback }}</div>

    <section class="admin-panel cms-page-summary">
        <div><span class="admin-badge">{{ $this->page->archived_at ? 'Archived' : ($candidateState ? str_replace('_', ' ', ucfirst($candidateState)) : ($publication?->current_public_revision_id ? 'Published' : 'Active draft')) }}</span><h2>{{ $this->page->title }}</h2><p>/{{ $this->page->slug }}</p></div>
        <dl class="admin-definition-grid">
            <div><dt>Type</dt><dd>{{ $this->page->type }}</dd></div><div><dt>Template</dt><dd>{{ $this->page->template_key }}</dd></div><div><dt>Locale</dt><dd>{{ $this->page->locale }}</dd></div>
            <div><dt>Draft version</dt><dd>{{ $this->page->currentDraftRevision->revision_number }}</dd></div>
            <div><dt>Version in review</dt><dd>{{ $publication?->candidateRevision?->revision_number ?? 'None' }}</dd></div>
            <div><dt>Published version</dt><dd>{{ $publication?->currentPublicRevision?->revision_number ?? 'None' }}</dd></div>
        </dl>
        @if($publication?->current_public_revision_id)<p class="admin-notice">This is the published version.</p>@endif
        @if($publication?->candidate_revision_id && $publication->candidate_revision_id !== $this->page->current_draft_revision_id)<p class="admin-warning">A newer unsubmitted draft exists. It does not change the version currently in review.</p>@endif
        <div class="admin-page-actions">
            @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_EDIT) @if(!$this->page->archived_at)<a class="admin-primary-button" href="{{ route('admin.content.pages.edit',$this->page) }}">Edit draft</a>@endif @endcan
            @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_PREVIEW)<button class="admin-secondary-button" type="button" wire:click="preview">Preview current revision</button>@endcan
            @if($publication?->candidateRevision) @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_PREVIEW)<button class="admin-secondary-button" type="button" wire:click="preview('{{ $publication->candidate_revision_id }}')">Preview version in review</button>@endcan @endif
            @if($publication?->currentPublicRevision) @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_PREVIEW)<button class="admin-secondary-button" type="button" wire:click="preview('{{ $publication->current_public_revision_id }}')">Preview published version</button>@endcan @endif
        </div>
    </section>

    <section class="admin-panel" aria-labelledby="readiness-title"><h2 id="readiness-title">Publication readiness</h2>
        @if($this->readiness['blockers'])<h3>Blockers</h3><ul>@foreach($this->readiness['blockers'] as $finding)<li>{{ $finding }}</li>@endforeach</ul>@else<p>No blocking readiness findings.</p>@endif
        @if($this->readiness['warnings'])<h3>Warnings</h3><ul>@foreach($this->readiness['warnings'] as $finding)<li>{{ $finding }}</li>@endforeach</ul>@endif
    </section>

    <section class="admin-panel" aria-labelledby="workflow-actions-title"><h2 id="workflow-actions-title">Workflow actions</h2>
        <div class="admin-field"><label for="workflow-note">Submission or review note</label><textarea id="workflow-note" wire:model="workflowNote" maxlength="2000"></textarea></div>
        @if($publication?->candidate_revision_id)<label class="admin-confirmation"><input type="checkbox" wire:model="confirmSupersession"> Replace the version currently in review with this draft</label>@endif
        <div class="admin-page-actions">
            @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_EDIT) @if(!$this->page->archived_at)<button class="admin-primary-button" type="button" wire:click="submit">Submit current draft for review</button>@endif @endcan
            @if(in_array($candidateState, ['in_review','approved'], true)) @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_REVIEW)<button class="admin-secondary-button" type="button" wire:click="requestChanges">Request changes</button>@endcan @endif
            @if($candidateState === 'in_review') @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_APPROVE)<button class="admin-primary-button" type="button" wire:click="approve">Approve</button>@endcan @endif
            @if($candidateState === 'approved') @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_PUBLISH)<button class="admin-primary-button" type="button" wire:click="publish">Publish now</button>@endcan @endif
        </div>
        @if($candidateState === 'approved')
            @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_SCHEDULE)
                @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_PUBLISH)
                    <div class="admin-field"><label for="schedule-local">Schedule in Africa/Dar_es_Salaam</label><input id="schedule-local" type="datetime-local" wire:model="scheduleLocal"><small>The stored instant is converted to UTC.</small></div>
                    <button class="admin-primary-button" type="button" wire:click="schedule">Schedule approved revision</button>
                @endcan
            @endcan
        @endif
        @if($candidateState === 'scheduled')<p><strong>Scheduled local:</strong> {{ $publication->scheduled_for->timezone('Africa/Dar_es_Salaam')->format('Y-m-d H:i T') }} <small>UTC {{ $publication->scheduled_for->utc()->format('Y-m-d H:i T') }}</small></p>@endif
    </section>

    @if($this->comparison)
    <section class="admin-panel cms-revision-comparison" aria-labelledby="comparison-title"><h2 id="comparison-title">Structured revision comparison</h2>
        <p>Baseline: {{ $this->comparison['baseline'] ? substr($this->comparison['baseline'], 0, 10) : 'Initial revision' }}</p>
        @if($this->comparison['metadata'])<h3>Metadata</h3><ul>@foreach($this->comparison['metadata'] as $change)<li>{{ str_replace('_',' ',$change) }}</li>@endforeach</ul>@endif
        <h3>Section changes</h3>@if($this->comparison['sections'])<ul>@foreach($this->comparison['sections'] as $change)<li><strong>{{ str_replace('_',' ',$change['change']) }}</strong>@if(isset($change['type'])) - {{ str_replace('_',' ',$change['type']) }}@endif @if(isset($change['fields'])) - {{ implode(', ', $change['fields']) }}@endif</li>@endforeach</ul>@else<p>No structured section changes.</p>@endif
    </section>
    @endif

    <section class="admin-panel"><h2>Section outline</h2><ol>@foreach($this->page->currentDraftRevision->payload['sections'] as $section)<li>{{ str_replace('_',' ',ucfirst($section['type'])) }}</li>@endforeach</ol></section>
    <section class="admin-panel"><h2>Version history</h2><div class="cms-revision-list">@foreach($this->page->revisions->sortByDesc('revision_number') as $revision)<article><strong>Version {{ $revision->revision_number }}</strong><span>{{ $revision->author->name }} - {{ $revision->created_at->timezone('Africa/Dar_es_Salaam')->format('Y-m-d H:i') }}</span><small>{{ $revision->change_summary ?: 'No change summary' }} - {{ substr($revision->checksum,0,12) }} - {{ count($revision->payload['sections']) }} sections</small>@can(\App\Domain\Identity\Support\PermissionRegistry::PUBLICATION_EMERGENCY_UNPUBLISH)<button type="button" wire:click="rollback('{{ $revision->id }}')">Create new draft from this version</button>@endcan</article>@endforeach</div></section>

    <section class="admin-panel"><h2>Immutable transition history</h2>
        @if($publication?->transitions?->isNotEmpty())<ol class="cms-transition-timeline">@foreach($publication->transitions->sortByDesc('occurred_at') as $transition)<li><strong>{{ str_replace('_',' ',ucfirst($transition->to_state)) }}</strong><span>{{ $transition->actor?->name ?? 'System' }} - {{ $transition->occurred_at->timezone('Africa/Dar_es_Salaam')->format('Y-m-d H:i T') }}</span>@if($transition->note)<p>{{ $transition->note }}</p>@endif @if($transition->reason)<p>Reason: {{ $transition->reason }}</p>@endif</li>@endforeach</ol>@else<p>No publishing transitions yet.</p>@endif
    </section>

    <section class="admin-panel"><h2>Lifecycle actions</h2><p>Cancel a schedule or unpublish a page before archiving it.</p><div class="admin-field"><label for="page-lifecycle-reason">Required reason</label><textarea id="page-lifecycle-reason" wire:model="reason" maxlength="2000"></textarea></div>
        <div class="admin-page-actions">
            @if($candidateState === 'scheduled') @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_SCHEDULE)<button class="admin-danger-button" wire:click="cancelSchedule">Cancel schedule</button>@endcan @endif
            @if($publication?->current_public_revision_id) @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_UNPUBLISH)<button class="admin-danger-button" wire:click="unpublish">Unpublish</button>@endcan @endif
            @if($this->page->archived_at) @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_RESTORE)<button class="admin-primary-button" wire:click="restore">Restore active draft</button>@endcan
            @else @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_ARCHIVE)<button class="admin-danger-button" wire:click="archive">Archive draft page</button>@endcan @endif
        </div>
    </section>
</div>
