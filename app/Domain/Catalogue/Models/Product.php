<?php

namespace App\Domain\Catalogue\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class Product extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['archived_at' => 'immutable_datetime', 'lock_version' => 'integer', 'base_price_minor' => 'integer', 'compare_at_price_minor' => 'integer'];
    }

    /** @return HasMany<ProductRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ProductRevision::class);
    }

    /** @return BelongsTo<ProductRevision, $this> */
    public function currentDraftRevision(): BelongsTo
    {
        return $this->belongsTo(ProductRevision::class, 'current_draft_revision_id');
    }

    /** @return HasMany<ProductOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('position');
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    /** @return BelongsToMany<ProductCategory, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'product_category_assignments')->withPivot(['is_primary', 'position'])->withTimestamps();
    }

    /** @return HasMany<ProductBadge, $this> */
    public function badges(): HasMany
    {
        return $this->hasMany(ProductBadge::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function defaultVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'default_variant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param Builder<self> $q
     * @return Builder<self>
     */
    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('archived_at');
    }

    protected static function booted(): void
    {
        self::updating(function (self $p): void {
            if ($p->isDirty('stable_key')) {
                throw new LogicException('Product stable keys are immutable.');
            }
        });
    }
}
