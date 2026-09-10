<?php

namespace App\Domain\Checkout\Models;

use App\Domain\Checkout\Enums\OrderStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Support\CommerceLifecycleMutation;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property OrderStatus $status */
final class Order extends Model
{
    use HasUlids;

    protected $table = 'commerce_orders';

    protected $guarded = [];

    protected $hidden = ['submission_key', 'request_fingerprint', 'cart_fingerprint', 'customer_snapshot', 'delivery_snapshot', 'confirmation_reference', 'cancellation_reason'];

    protected function casts(): array
    {
        return ['customer_snapshot' => 'array', 'delivery_snapshot' => 'array', 'subtotal_minor' => 'integer', 'total_minor' => 'integer', 'status' => OrderStatus::class, 'placed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(function (self $order) {
            if (! app(CommerceLifecycleMutation::class)->active() || array_diff(array_keys($order->getDirty()), ['status', 'payment_status', 'confirmed_at', 'closed_at', 'updated_at', 'cancellation_reason', 'cancelled_at', 'cancelled_by']) !== []) {
                throw new \LogicException('Placed order snapshots are immutable; lifecycle changes require verified payment evidence.');
            }
            if ($order->isDirty(['cancellation_reason', 'cancelled_at', 'cancelled_by']) && ($order->getOriginal('cancelled_at') !== null || $order->status !== OrderStatus::Cancelled || $order->payment_status !== 'unpaid')) {
                throw new \LogicException('Cancellation history is permanent and requires an unpaid cancelled Order.');
            }
        });
        self::deleting(fn () => throw new \LogicException('Placed orders cannot be deleted.'));
    }

    /** @return HasMany<OrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class, 'order_id')->orderBy('id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id');
    }
}
