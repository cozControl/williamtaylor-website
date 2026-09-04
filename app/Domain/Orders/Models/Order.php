<?php

namespace App\Domain\Orders\Models;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $order_number
 * @property bool $is_demo
 * @property string $currency
 * @property int $subtotal_minor
 * @property int $adjustment_minor
 * @property int $total_minor
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property int $lock_version
 * @property string|null $receipt_reference
 */ final class Order extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_demo' => 'boolean',
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal_minor' => 'integer',
            'adjustment_minor' => 'integer',
            'total_minor' => 'integer',
            'lock_version' => 'integer',
            'confirmed_at' => 'immutable_datetime',
            'ready_at' => 'immutable_datetime',
            'dispatched_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('position');
    }

    /** @return HasMany<OrderStatusEvent, $this> */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(OrderStatusEvent::class)->orderBy('created_at');
    }

    /** @return HasMany<OrderNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(OrderNote::class)->orderBy('created_at');
    }

    /** @return HasMany<OrderPaymentEvent, $this> */
    public function paymentEvents(): HasMany
    {
        return $this->hasMany(OrderPaymentEvent::class)->orderBy('created_at');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
