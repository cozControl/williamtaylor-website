<?php

namespace App\Domain\Campaign\Support;

use App\Domain\Campaign\Models\Campaign;
use App\Domain\Catalogue\Support\ProductPresenter;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

final class CampaignPublicProjection
{
    public function __construct(
        private CampaignEffectiveStateEvaluator $states,
        private ProductPresenter $products,
        private MediaProvider $media,
    ) {}

    /** @return array<string, mixed>|null */
    public function resolve(Campaign $campaign, string $type): ?array
    {
        foreach (['campaign_products', 'campaign_claims', 'products', 'media_assets', 'media_usages'] as $table) {
            if (! Schema::hasTable($table)) {
                return null;
            }
        }

        if ($campaign->campaign_type !== $type || $this->states->evaluate($campaign, CarbonImmutable::now('UTC')) !== 'active') {
            return null;
        }

        $revision = $campaign->approvedRevision;
        $campaign->loadMissing(['products.product', 'claims', 'cardMedia.asset']);
        $target = $campaign->products->whereNull('archived_at')->sortBy('position')->first();
        $product = $target?->product === null ? null : $this->products->resolve($target->product->slug);
        $usage = $campaign->cardMedia->first();
        $asset = $usage?->asset;
        $alt = trim((string) ($usage?->alt_text_override ?: $asset?->default_alt_text));

        if ($revision === null || $product === null || $asset === null || $asset->state !== MediaAssetState::Ready || $asset->resource_type !== MediaResourceType::Image || $asset->confirmed_at === null || $asset->archived_at !== null || $alt === '' || $alt !== strip_tags($alt)) {
            return null;
        }

        $claims = $campaign->claims->whereNull('archived_at')->where('approval_status', 'approved')
            ->filter(fn ($claim): bool => filled($claim->approved_checksum) && hash_equals($claim->value_checksum, $claim->approved_checksum))
            ->pluck('normalized_value', 'claim_key')->all();

        return [
            'id' => $campaign->id,
            'headline' => $revision->headline,
            'summary' => $revision->summary,
            'cta_label' => $revision->cta_label,
            'product' => $product,
            'url' => route('products.show', $product['slug']),
            'image' => $this->media->deliveryUrl($asset->provider_public_id, $asset->resource_type->value, 'product_card', $asset->focal_x === null ? null : (float) $asset->focal_x, $asset->focal_y === null ? null : (float) $asset->focal_y),
            'alt' => $alt,
            'starts_at' => $campaign->starts_at?->toIso8601String(),
            'ends_at' => $campaign->ends_at?->toIso8601String(),
            'claims' => $claims,
        ];
    }
}
