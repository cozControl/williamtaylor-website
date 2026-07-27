<?php

namespace App\Livewire\Admin\Content\Pages;

use App\Domain\Content\Support\PageTypeRegistry;
use App\Domain\Publishing\Models\PagePublicationState;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class ReviewQueue extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: 'in_review')]
    public string $state = 'in_review';

    #[Url(except: 'transition')]
    public string $sort = 'transition';

    public function updated(): void
    {
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<int, PagePublicationState> */
    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $allowedStates = ['in_review', 'changes_requested', 'approved', 'scheduled', 'published', 'unpublished'];
        $sorts = [
            'transition' => ['last_transition_at', 'desc'],
            'schedule' => ['scheduled_for', 'asc'],
            'title' => ['pages.title', 'asc'],
        ];
        [$column, $direction] = $sorts[$this->sort] ?? $sorts['transition'];
        $search = mb_substr(trim($this->search), 0, 100);

        return PagePublicationState::query()
            ->join('pages', 'pages.id', '=', 'page_publication_states.page_id')
            ->select('page_publication_states.*')
            ->with([
                'page:id,title,slug,type,locale,current_draft_revision_id',
                'candidateRevision:id,revision_number',
                'currentPublicRevision:id,revision_number',
                'submitter:id,name',
                'approver:id,name',
                'transitions' => fn ($query) => $query->latest('occurred_at')->limit(1),
            ])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $nested) => $nested->where('pages.title', 'like', "%{$search}%")->orWhere('pages.slug', 'like', "%{$search}%")))
            ->when(array_key_exists($this->type, app(PageTypeRegistry::class)->all()), fn (Builder $query) => $query->where('pages.type', $this->type))
            ->when(in_array($this->state, $allowedStates, true), function (Builder $query): void {
                if ($this->state === 'published') {
                    $query->whereNotNull('current_public_revision_id');
                } elseif ($this->state === 'unpublished') {
                    $query->whereNull('current_public_revision_id')->whereNull('candidate_state')->whereHas('transitions', fn ($transition) => $transition->where('to_state', 'unpublished'));
                } else {
                    $query->where('candidate_state', $this->state);
                }
            })
            ->orderBy($column, $direction)
            ->orderBy('page_publication_states.id')
            ->paginate(20)
            ->withQueryString();
    }

    public function render(): View
    {
        return view('livewire.admin.content.pages.review-queue', ['types' => app(PageTypeRegistry::class)->all()]);
    }
}
