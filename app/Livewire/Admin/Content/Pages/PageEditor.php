<?php

namespace App\Livewire\Admin\Content\Pages;

use App\Domain\Content\Actions\CreatePagePreviewUrl;
use App\Domain\Content\Actions\SavePageDraftRevision;
use App\Domain\Content\Exceptions\StaleDraftException;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Support\SectionRegistry;
use App\Domain\Content\Support\TemplateRegistry;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class PageEditor extends Component
{
    public string $pageId;

    public string $expectedRevisionId;

    public string $title;

    public string $slug;

    public string $templateKey;

    /** @var list<array<string, mixed>> */
    public array $sections = [];

    public int $selected = 0;

    public string $changeSummary = '';

    public string $feedback = '';

    public string $feedbackType = 'success';

    public bool $dirty = false;

    public function mount(string $pageId): void
    {
        $page = Page::query()->with('currentDraftRevision')->findOrFail($pageId);
        $this->pageId = $page->getKey();
        $this->expectedRevisionId = $page->current_draft_revision_id;
        $this->title = $page->title;
        $this->slug = $page->slug;
        $this->templateKey = $page->template_key;
        $this->sections = $page->currentDraftRevision->payload['sections'];
    }

    public function updated(): void
    {
        $this->dirty = true;
    }

    #[Computed]
    public function page(): Page
    {
        return Page::query()->with(['currentDraftRevision', 'revisions', 'publicationState.candidateRevision', 'publicationState.currentPublicRevision'])->findOrFail($this->pageId);
    }

    #[Computed]
    public function media(): mixed
    {
        return MediaAsset::query()->where('state', MediaAssetState::Ready)->orderBy('internal_title')->limit(100)->get();
    }

    public function selectSection(int $index): void
    {
        abort_unless(isset($this->sections[$index]), 404);
        $this->selected = $index;
    }

    public function addSection(string $type): void
    {
        $definition = app(SectionRegistry::class)->all()[$type] ?? abort(422);
        $this->sections[] = ['key' => (string) Str::ulid(), 'type' => $type, 'schema_version' => $definition['schema_version'], 'data' => $this->defaults($type)];
        $this->selected = count($this->sections) - 1;
        $this->dirty = true;
    }

    public function moveSection(int $from, int $direction): void
    {
        $to = $from + $direction;
        if (! isset($this->sections[$from], $this->sections[$to])) {
            return;
        }
        [$this->sections[$from], $this->sections[$to]] = [$this->sections[$to], $this->sections[$from]];
        $this->selected = $to;
        $this->dirty = true;
        $this->dispatch('section-order-changed', position: $to + 1);
    }

    public function duplicateSection(int $index): void
    {
        $copy = $this->sections[$index] ?? abort(404);
        $copy['key'] = (string) Str::ulid();
        array_splice($this->sections, $index + 1, 0, [$copy]);
        $this->selected = $index + 1;
        $this->dirty = true;
    }

    public function removeSection(int $index): void
    {
        if (count($this->sections) <= 1) {
            $this->error('A page must retain at least one section.');

            return;
        }
        array_splice($this->sections, $index, 1);
        $this->selected = min($this->selected, count($this->sections) - 1);
        $this->dirty = true;
    }

    public function save(SavePageDraftRevision $action): void
    {
        try {
            $revision = $action->handle($this->actor(), $this->page(), $this->expectedRevisionId, $this->title, $this->slug, $this->templateKey, $this->sections, $this->changeSummary);
        } catch (StaleDraftException $exception) {
            $this->error($exception->getMessage());
            $this->dispatch('draft-conflict');

            return;
        }
        $this->expectedRevisionId = $revision->getKey();
        $this->changeSummary = '';
        $this->dirty = false;
        unset($this->page);
        $this->success('Draft revision '.$revision->revision_number.' saved immutably.');
    }

    public function preview(CreatePagePreviewUrl $action): void
    {
        $revision = $this->page()->revisions()->findOrFail($this->expectedRevisionId);
        $this->dispatch('open-page-preview', url: $action->handle($this->actor(), $this->page(), $revision));
    }

    public function render(): View
    {
        return view('livewire.admin.content.pages.page-editor', ['registry' => app(SectionRegistry::class)->all(), 'templates' => app(TemplateRegistry::class)->all()]);
    }

    /** @return array<string, mixed> */
    private function defaults(string $type): array
    {
        return match ($type) {
            'hero' => ['eyebrow' => '', 'heading' => 'New hero', 'copy' => '', 'alignment' => 'left', 'variant' => 'light'],
            'editorial_split' => ['heading' => 'New editorial section', 'copy' => '', 'media_position' => 'left', 'variant' => 'plain'],
            'promotional_cards' => ['heading' => '', 'cards' => [['key' => (string) Str::ulid(), 'heading' => 'New card', 'copy' => '', 'variant' => 'portrait']]],
            'rich_text' => ['document' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => []]]]],
            'cta' => ['heading' => 'New call to action', 'copy' => '', 'primary_cta' => ['kind' => 'internal_path', 'label' => 'Learn more', 'target' => '/'], 'variant' => 'light'],
            default => throw new \InvalidArgumentException('Unknown section type.'),
        };
    }

    private function success(string $message): void
    {
        $this->feedback = $message;
        $this->feedbackType = 'success';
    }

    private function error(string $message): void
    {
        $this->feedback = $message;
        $this->feedbackType = 'error';
    }

    private function actor(): User
    {
        return auth()->user() instanceof User ? auth()->user() : abort(403);
    }
}
