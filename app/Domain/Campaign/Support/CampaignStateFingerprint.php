<?php

namespace App\Domain\Campaign\Support;

use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignClaim;
use App\Domain\Campaign\Models\CampaignProduct;
use App\Domain\Media\Models\MediaUsage;

final class CampaignStateFingerprint
{
    public function identity(Campaign $c): string
    {
        return $this->hash([$c->id, $c->campaign_type, $c->current_draft_revision_id, $c->approved_revision_id, $c->starts_at?->toISOString(), $c->ends_at?->toISOString(), $c->archived_at !== null, $c->lock_version]);
    }

    public function targets(string $id): string
    {
        return $this->hash(CampaignProduct::query()->active()->where('campaign_id', $id)->orderBy('id')->get(['id', 'product_id', 'position'])->toArray());
    }

    public function claims(string $id): string
    {
        return $this->hash(CampaignClaim::query()->where('campaign_id', $id)->orderBy('id')->get(['id', 'claim_key', 'value_checksum', 'approval_status', 'approved_by', 'archived_at'])->toArray());
    }

    public function media(string $id): string
    {
        return $this->hash(MediaUsage::query()->where('owner_type', Campaign::class)->where('owner_identifier', $id)->orderBy('id')->get(['id', 'media_asset_id', 'field_role', 'sort_order', 'alt_text_override'])->toArray());
    }

    private function hash(mixed $v): string
    {
        return hash('sha256', json_encode($v, JSON_THROW_ON_ERROR));
    }
}
