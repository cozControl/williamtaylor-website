<?php

namespace App\Domain\Catalogue\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class ProductVariant extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['archived_at' => 'immutable_datetime', 'lock_version' => 'integer', 'position' => 'integer', 'price_override_minor' => 'integer', 'compare_at_price_override_minor' => 'integer'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsToMany<ProductOptionValue, $this> */
    public function values(): BelongsToMany
    {
        return $this->belongsToMany(ProductOptionValue::class, 'product_variant_values', 'variant_id', 'product_option_value_id')->withPivot('product_option_id', 'created_at');
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
}
