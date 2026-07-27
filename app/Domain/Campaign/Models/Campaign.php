<?php

namespace App\Domain\Campaign\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable|null $archived_at
 */
final class Campaign extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'archived_at' => 'immutable_datetime', 'lock_version' => 'integer'];
    }

    /** @return BelongsTo<CampaignRevision, $this> */
    public function currentDraftRevision(): BelongsTo
    {
        return $this->belongsTo(CampaignRevision::class, 'current_draft_revision_id');
    }

    /** @return BelongsTo<CampaignRevision, $this> */
    public function approvedRevision(): BelongsTo
    {
        return $this->belongsTo(CampaignRevision::class, 'approved_revision_id');
    }

    /** @return HasMany<CampaignProduct, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(CampaignProduct::class)->orderBy('position');
    }

    /** @return HasMany<CampaignClaim, $this> */
    public function claims(): HasMany
    {
        return $this->hasMany(CampaignClaim::class);
    }
}
