<?php

namespace App\Domain\Campaign\Support;

use App\Domain\Campaign\Data\CampaignReadinessResult;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignProduct;
use App\Domain\Catalogue\Support\CatalogueReadinessEvaluator;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaUsage;

final class CampaignReadinessEvaluator
{
    public function __construct(private CampaignTypeRegistry $types, private CatalogueReadinessEvaluator $products) {}

    public function evaluate(Campaign $campaign): CampaignReadinessResult
    {
        $f = [];
        if ($campaign->archived_at) {
            $f[] = 'campaign_archived';
        }try {
            $def = $this->types->get($campaign->campaign_type);
        } catch (\Throwable) {
            $f[] = 'invalid_campaign_type';
            $def = null;
        }
        if (! $campaign->currentDraftRevision) {
            $f[] = 'missing_current_revision';
        }if (! $campaign->approved_revision_id) {
            $f[] = 'missing_approved_revision';
        }
        if (! $campaign->starts_at || ! $campaign->ends_at || $campaign->ends_at->lessThanOrEqualTo($campaign->starts_at)) {
            $f[] = 'invalid_schedule';
        }
        $targets = CampaignProduct::query()->active()->with('product')->where('campaign_id', $campaign->id)->orderBy('position')->get();
        if ($targets->isEmpty()) {
            $f[] = 'missing_targets';
        } elseif ($targets->pluck('position')->all() !== range(0, $targets->count() - 1)) {
            $f[] = 'invalid_target_order';
        }
        foreach ($targets as $target) {
            if (! $target->product || $target->product->archived_at) {
                $f[] = 'archived_product_target';
            } elseif (! $this->products->evaluate($target->product)->ready) {
                $f[] = 'product_target_not_catalogue_ready';
            }
        }
        $media = MediaUsage::query()->with('asset')->where('owner_type', Campaign::class)->where('owner_identifier', $campaign->id)->where('field_role', 'card')->get();
        if ($media->count() !== 1) {
            $f[] = 'missing_card_media';
        } else {
            $m = $media->first();
            $alt = trim($m->alt_text_override ?? (string) $m->asset?->default_alt_text);
            if (! $m->asset || $m->asset->state !== MediaAssetState::Ready || $m->asset->resource_type !== MediaResourceType::Image || $m->asset->confirmed_at === null || $m->decorative_override || $alt === '' || $alt !== strip_tags($alt)) {
                $f[] = 'unusable_card_media';
            }
        }
        foreach ($def['required_claims'] ?? [] as $key) {
            $claim = $campaign->claims()->active()->where('claim_key', $key)->first();
            if (! $claim) {
                $f[] = 'missing_required_claim';
            } elseif ($claim->approval_status !== 'approved' || ! $claim->approved_by || ! $claim->approved_at || ! $claim->approved_checksum || ! hash_equals($claim->value_checksum, $claim->approved_checksum)) {
                $f[] = 'unapproved_required_claim';
            }
        }
        $f = array_values(array_unique($f));
        sort($f);

        return new CampaignReadinessResult($f === [], $f);
    }
}
