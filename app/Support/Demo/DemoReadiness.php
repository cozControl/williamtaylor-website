<?php

namespace App\Support\Demo;

use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\SiteContent\Models\SiteContent;
use App\Domain\SiteContent\Support\SiteContentSchema;
use App\Domain\SiteContent\Support\SiteContentTypeRegistry;
use Illuminate\Support\Collection;
use Throwable;

final class DemoReadiness
{
    public function __construct(
        private DemoMode $mode,
        private SiteContentSchema $schema,
    ) {}

    /**
     * @return array{passed: bool, failures: list<string>, resources: Collection<int, SiteContent>}
     */
    public function evaluate(): array
    {
        $failures = [];
        $resources = SiteContent::query()
            ->with(['currentDraftRevision.author', 'publicationState.candidateRevision', 'publicationState.currentPublicRevision', 'publicationState.transitions'])
            ->get();

        if (! $this->mode->configured()) {
            $failures[] = 'Demo projection is disabled.';
        }
        if (! config('publication_rollout.global_enabled', false)) {
            $failures[] = 'The global publication kill switch is active.';
        }

        foreach (app(SiteContentTypeRegistry::class)->all() as $type => $definition) {
            $matches = $resources->where('type', $type)->whereNull('archived_at');
            if ($definition->singleton && $matches->isEmpty()) {
                $failures[] = "{$definition->label}: missing draft.";
            }
            foreach ($matches as $resource) {
                if (! $resource->currentDraftRevision) {
                    $failures[] = "{$definition->label}: missing draft.";

                    continue;
                }
                try {
                    $this->schema->validate($type, $resource->currentDraftRevision->payload);
                } catch (Throwable) {
                    $failures[] = "{$resource->title}: draft validation failed.";
                }
                if ($resource->publicationState?->candidate_revision_id && $resource->publicationState->candidate_state?->value !== 'approved') {
                    $failures[] = "{$resource->title}: revision is not approved.";
                }
                if ($type === SiteContentTypeRegistry::SITE_PROFILE) {
                    $this->profileFailures($resource->currentDraftRevision->payload, $failures);
                }
            }
        }

        return ['passed' => $failures === [], 'failures' => array_values(array_unique($failures)), 'resources' => $resources];
    }

    /** @param array<string, mixed> $payload
     * @param  list<string>  $failures
     */
    private function profileFailures(array $payload, array &$failures): void
    {
        if (blank(data_get($payload, 'contact.email')) && blank(data_get($payload, 'contact.telephone'))) {
            $failures[] = 'Site settings: add an email address or telephone number.';
        }
        foreach (['brand.header_logo_id', 'brand.footer_logo_id', 'footer.footer_image_id'] as $field) {
            $id = data_get($payload, $field);
            if (! is_string($id) || $id === '') {
                continue;
            }
            $asset = MediaAsset::query()->find($id);
            if (! $asset || $asset->state !== MediaAssetState::Ready || $asset->archived_at || $asset->resource_type !== MediaResourceType::Image) {
                $failures[] = "Site settings: {$field} must reference a ready image.";
            } elseif (! $asset->is_decorative && blank($asset->default_alt_text)) {
                $failures[] = "Site settings: {$field} is missing effective alt text.";
            }
        }
    }
}
