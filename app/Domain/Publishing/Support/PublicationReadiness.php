<?php

namespace App\Domain\Publishing\Support;

use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Support\PageTypeRegistry;
use App\Domain\Content\Support\SectionRegistry;
use App\Domain\Content\Support\TemplateRegistry;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Models\MediaUsage;
use InvalidArgumentException;

final class PublicationReadiness
{
    public function __construct(
        private PageTypeRegistry $types,
        private TemplateRegistry $templates,
        private SectionRegistry $sections,
    ) {}

    /** @return array{blockers: list<string>, warnings: list<string>} */
    public function inspect(Page $page, ContentRevision $revision): array
    {
        $blockers = [];
        $warnings = [];

        if ($revision->resource_type !== Page::class || $revision->resource_id !== $page->getKey()) {
            $blockers[] = 'Candidate revision does not belong to this Page.';

            return compact('blockers', 'warnings');
        }

        try {
            $type = $this->types->get($page->type);
            $this->templates->get($page->template_key);
            $sections = $revision->payload['sections'] ?? [];
            if (! is_array($sections) || count($sections) < $type['min'] || count($sections) > $type['max']) {
                throw new InvalidArgumentException('Section count is incompatible with the Page type.');
            }
            foreach ($sections as $section) {
                $this->sections->normalize($section, $page->type);
            }
        } catch (\Throwable $exception) {
            $blockers[] = $exception->getMessage();
        }

        $invalidMedia = MediaUsage::query()
            ->where('owner_type', ContentRevision::class)
            ->where('owner_identifier', $revision->getKey())
            ->whereHas('asset', fn ($query) => $query->where('state', '!=', MediaAssetState::Ready->value))
            ->exists();
        if ($invalidMedia) {
            $blockers[] = 'Candidate references Media that is no longer ready.';
        }
        if (trim($page->title) === '') {
            $blockers[] = 'Page title is required.';
        }
        if ($page->current_draft_revision_id !== $revision->getKey()) {
            $warnings[] = 'A newer unsubmitted draft exists.';
        }

        return compact('blockers', 'warnings');
    }

    public function ensureReady(Page $page, ContentRevision $revision): void
    {
        $result = $this->inspect($page, $revision);
        if ($result['blockers'] !== []) {
            throw new InvalidArgumentException(implode(' ', $result['blockers']));
        }
    }
}
