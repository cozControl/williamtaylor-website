<?php

namespace App\Domain\Catalogue\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Collection extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['archived_at' => 'immutable_datetime', 'lock_version' => 'integer', 'navigation_order' => 'integer'];
    }

    /** @return HasMany<CollectionRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(CollectionRevision::class);
    }

    /** @return BelongsTo<CollectionRevision, $this> */
    public function currentDraftRevision(): BelongsTo
    {
        return $this->belongsTo(CollectionRevision::class, 'current_draft_revision_id');
    }

    /** @return HasMany<CollectionProduct, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(CollectionProduct::class)->active()->orderBy('position');
    }

    /** @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
