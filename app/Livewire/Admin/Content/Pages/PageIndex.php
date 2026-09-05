<?php

namespace App\Livewire\Admin\Content\Pages;

use App\Domain\Content\Models\Page;
use App\Domain\Content\Support\PageTypeRegistry;
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

    #[Url(except: 'updated')]
    public string $sort = 'updated';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'type');
        $this->state = 'active';
        $this->sort = 'updated';
        $this->resetPage();
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
            ->orderBy($column, $direction)->orderBy('id')->paginate(20)->withQueryString();

        return $pages;
    }

    public function render(): View
    {
        return view('livewire.admin.content.pages.page-index', ['types' => app(PageTypeRegistry::class)->all()]);
    }
}
