<?php

namespace App\Domain\Catalogue\Models;

use App\Domain\Media\Models\MediaAsset;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProductCategory extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'position' => 'integer', 'archived_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<ProductCategory, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<ProductCategory, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('name');
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_category_assignments')->withPivot(['is_primary', 'position'])->withTimestamps();
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function image(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'image_media_asset_id');
    }
}
