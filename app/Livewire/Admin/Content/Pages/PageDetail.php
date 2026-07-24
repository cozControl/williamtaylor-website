<?php

namespace App\Livewire\Admin\Content\Pages;

use App\Domain\Content\Actions\ArchivePage;
use App\Domain\Content\Actions\CreatePagePreviewUrl;
use App\Domain\Content\Actions\RestorePage;
use App\Domain\Content\Models\Page;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class PageDetail extends Component
{
    public string $pageId;

    public string $reason = '';

    public string $feedback = '';

    public function mount(string $pageId): void
    {
        $this->pageId = $pageId;
    }

    #[Computed]
    public function page(): Page
    {
        return Page::query()->with(['currentDraftRevision.author', 'revisions.author', 'creator', 'updater'])->findOrFail($this->pageId);
    }

    public function preview(CreatePagePreviewUrl $action): void
    {
        $this->dispatch('open-page-preview', url: $action->handle($this->actor(), $this->page(), $this->page()->currentDraftRevision));
    }

    public function archive(ArchivePage $action): void
    {
        $action->handle($this->actor(), $this->page(), $this->reason);
        unset($this->page);
        $this->reason = '';
        $this->feedback = 'Page archived. Revisions and preview history remain available.';
    }

    public function restore(RestorePage $action): void
    {
        $action->handle($this->actor(), $this->page(), $this->reason);
        unset($this->page);
        $this->reason = '';
        $this->feedback = 'Page restored to active draft.';
    }

    public function render(): View
    {
        return view('livewire.admin.content.pages.page-detail');
    }

    private function actor(): User
    {
        return auth()->user() instanceof User ? auth()->user() : abort(403);
    }
}
