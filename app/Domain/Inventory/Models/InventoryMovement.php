<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Inventory\Enums\MovementType;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** @property MovementType $type */
final class InventoryMovement extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['type' => MovementType::class, 'quantity_delta' => 'integer', 'balance_after' => 'integer', 'occurred_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /** @return BelongsTo<StockLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Posted stock movements cannot be edited. Post a corrective adjustment.'));
        self::deleting(fn () => throw new LogicException('Posted stock movements cannot be deleted. Post a corrective adjustment.'));
    }
}
