<?php

namespace App\Domain\Publishing\Support;

use App\Domain\Content\Models\Page;
use App\Domain\Content\Support\PageTypeRegistry;
use App\Domain\Content\Support\SectionRegistry;
use App\Domain\Content\Support\TemplateRegistry;
use App\Domain\Publishing\Contracts\PublicationPolicy;
use App\Domain\Publishing\Models\PagePublicationState;

final class PublicationFingerprint
{
    public function __construct(
        private PublicationReadiness $readiness,
        private PublicationPolicy $policy,
        private PageTypeRegistry $types,
        private TemplateRegistry $templates,
        private SectionRegistry $sections,
    ) {}

    public function for(Page $page, ?PagePublicationState $state = null): string
    {
        $state ??= $page->publicationState;
        $candidate = $state?->candidateRevision;
        $readiness = $candidate === null ? null : $this->readiness->inspect($page, $candidate);
        $data = [
            'page' => $page->getKey(),
            'draft' => $page->current_draft_revision_id,
            'candidate' => $state?->candidate_revision_id,
            'checksum' => $candidate?->checksum,
            'state' => $state?->candidate_state?->value,
            'public' => $state?->current_public_revision_id,
            'schedule' => $state?->scheduled_for?->utc()->toIso8601String(),
            'version' => $state === null ? 0 : $state->state_version,
            'readiness' => $readiness,
            'policy' => $this->policy->checksum(),
            'registries' => [
                hash('sha256', json_encode($this->types->all(), JSON_THROW_ON_ERROR)),
                hash('sha256', json_encode($this->templates->all(), JSON_THROW_ON_ERROR)),
                hash('sha256', json_encode($this->sections->all(), JSON_THROW_ON_ERROR)),
            ],
        ];

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
