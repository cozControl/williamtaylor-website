<?php

namespace App\Domain\SiteContent\Support;

use App\Domain\SiteContent\Models\SiteContent;
use App\Domain\SiteContent\Models\SiteContentPublicationState;

final class SiteContentFingerprint
{
    public function __construct(private SiteContentTypeRegistry $types) {}

    public function for(SiteContent $content, ?SiteContentPublicationState $state = null): string
    {
        $state ??= $content->publicationState;
        $candidate = $state?->candidateRevision;

        return hash('sha256', json_encode([
            'content' => $content->getKey(),
            'type' => $content->type,
            'draft' => $content->current_draft_revision_id,
            'candidate' => $state?->candidate_revision_id,
            'candidate_checksum' => $candidate?->checksum,
            'candidate_state' => $state?->candidate_state?->value,
            'public' => $state?->current_public_revision_id,
            'scheduled_for' => $state?->scheduled_for?->utc()->toIso8601String(),
            'state_version' => $state === null ? 0 : $state->state_version,
            'schema_version' => SiteContentSchema::VERSION,
            'type_policy' => $this->types->get($content->type)->policyChecksum(),
            'type_registry' => $this->types->checksum(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
