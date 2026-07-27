<?php

namespace App\Domain\Campaign\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class CampaignClaim extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['submitted_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime', 'rejected_at' => 'immutable_datetime', 'archived_at' => 'immutable_datetime'];
    }

    /** @param Builder<self> $query
     * @return Builder<self> */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
