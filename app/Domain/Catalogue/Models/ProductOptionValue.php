<?php

namespace App\Domain\Catalogue\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductOptionValue extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['archived_at' => 'immutable_datetime', 'position' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<ProductOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }

    /** @param Builder<self> $q
     * @return Builder<self>
     */
    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('archived_at');
    }
}
