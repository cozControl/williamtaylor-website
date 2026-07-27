<?php

namespace App\Domain\Merchandising\Models;

use App\Domain\Catalogue\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductRelation extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['position' => 'integer', 'archived_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Product, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'source_product_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'target_product_id');
    }

    /** @return BelongsTo<User, $this> */
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
