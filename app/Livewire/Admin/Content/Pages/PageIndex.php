<?php

namespace App\Livewire\Admin\Content\Pages;

use App\Domain\Content\Models\Page;
use App\Domain\Content\Support\PageTypeRegistry;
use App\Domain\Publishing\Enums\CandidateState;
use App\Domain\Publishing\Support\PublicationReadiness;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class PageIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: 'active')]
    public string $state = 'active';

    #[Url(except: '')]
    public string $readiness = '';

    #[Url(except: '')]
    public string $publication = '';

    #[Url(except: 'updated')]
    public string $sort = 'updated';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'type', 'readiness', 'publication');
        $this->state = 'active';
        $this->sort = 'updated';
        $this->resetPage();
    }

    /** @return array{draft: string, workflow: string, publication: string, readiness: string, media: string, blockers: list<string>} */
    public function status(Page $page): array
    {
        $revision = $page->currentDraftRevision;
        $blockers = $revision === null ? ['No active draft is available.'] : app(PublicationReadiness::class)->inspect($page, $revision)['blockers'];
        $candidate = $page->publicationState?->candidate_state;

        return [
            'draft' => $page->archived_at !== null ? 'Archived' : ($revision === null ? 'No draft' : 'Draft revision '.$revision->revision_number),
            'workflow' => match ($candidate) {
                CandidateState::InReview => 'Awaiting review',
                CandidateState::ChangesRequested => 'Changes requested',
                CandidateState::Approved => 'Approved',
                CandidateState::Scheduled => 'Scheduled',
                default => 'Not submitted',
            },
            'publication' => $page->publicationState?->current_public_revision_id ? 'Published to demo' : 'Static fallback',
            'readiness' => $blockers === [] ? 'Ready' : 'Needs attention',
            'media' => collect($blockers)->contains(fn (string $blocker) => str_contains(strtolower($blocker), 'media') || str_contains(strtolower($blocker), 'alt text')) ? 'Needs attention' : 'Ready',
            'blockers' => $blockers,
        ];
    }

    /** @return LengthAwarePaginator<int, Page> */
    #[Computed]
    public function pages(): LengthAwarePaginator
    {
        $sorts = ['updated' => ['updated_at', 'desc'], 'created' => ['created_at', 'desc'], 'title' => ['title', 'asc']];
        [$column, $direction] = $sorts[$this->sort] ?? $sorts['updated'];
        $search = mb_substr(trim($this->search), 0, 100);

        $pages = Page::query()->with(['currentDraftRevision.mediaUsages.asset', 'updater:id,name', 'publicationState.candidateRevision', 'publicationState.currentPublicRevision'])
            ->withCount('revisions')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $nested) => $nested->where('title', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")))
            ->when(array_key_exists($this->type, app(PageTypeRegistry::class)->all()), fn (Builder $query) => $query->where('type', $this->type))
            ->when($this->state === 'active', fn (Builder $query) => $query->whereNull('archived_at'))
            ->when($this->state === 'archived', fn (Builder $query) => $query->whereNotNull('archived_at'))
            ->when($this->publication === 'published', fn (Builder $query) => $query->whereHas('publicationState', fn (Builder $state) => $state->whereNotNull('current_public_revision_id')))
            ->when($this->publication === 'unpublished', fn (Builder $query) => $query->whereDoesntHave('publicationState', fn (Builder $state) => $state->whereNotNull('current_public_revision_id')))
            ->when($this->publication === 'review', fn (Builder $query) => $query->whereHas('publicationState', fn (Builder $state) => $state->where('candidate_state', CandidateState::InReview->value)))
            ->when($this->publication === 'approved', fn (Builder $query) => $query->whereHas('publicationState', fn (Builder $state) => $state->where('candidate_state', CandidateState::Approved->value)->whereNull('current_public_revision_id')))
            ->orderBy($column, $direction)->orderBy('id')->paginate(20)->withQueryString();

        if ($this->readiness !== '') {
            $inspector = app(PublicationReadiness::class);
            $pages->setCollection($pages->getCollection()->filter(function (Page $page) use ($inspector): bool {
                $ready = $page->currentDraftRevision !== null && $inspector->inspect($page, $page->currentDraftRevision)['blockers'] === [];

                return $this->readiness === ($ready ? 'ready' : 'blocked');
            })->values());
        }

        return $pages;
    }

    public function render(): View
    {
        return view('livewire.admin.content.pages.page-index', ['types' => app(PageTypeRegistry::class)->all()]);
    }
}
