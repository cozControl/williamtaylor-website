<?php

namespace App\Livewire\Admin\Content\Pages;

use App\Domain\Content\Actions\CreatePageDraft;
use App\Domain\Content\Support\PageTypeRegistry;
use App\Domain\Content\Support\TemplateRegistry;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class PageCreate extends Component
{
    public string $type = 'standard';

    public string $title = '';

    public string $slug = '';

    public string $locale = 'en';

    public string $templateKey = 'standard_page';

    public function updatedType(string $type): void
    {
        $this->templateKey = app(PageTypeRegistry::class)->get($type)['templates'][0];
    }

    public function create(CreatePageDraft $action): mixed
    {
        $page = $action->handle($this->actor(), $this->type, $this->title, $this->slug, $this->locale, $this->templateKey);

        return $this->redirectRoute('admin.content.pages.edit', $page, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.content.pages.page-create', ['types' => app(PageTypeRegistry::class)->all(), 'templates' => app(TemplateRegistry::class)->all()]);
    }

    private function actor(): User
    {
        return auth()->user() instanceof User ? auth()->user() : abort(403);
    }
}
