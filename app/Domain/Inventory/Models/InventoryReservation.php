<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Payments\Support\CommerceLifecycleMutation;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** @property ReservationStatus $status */
final class InventoryReservation extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'status' => ReservationStatus::class, 'reserved_at' => 'immutable_datetime', 'released_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $reservation) {
            if ($reservation->quantity < 1 || $reservation->quantity > 2147483647) {
                throw new \InvalidArgumentException('Reservation quantity must be positive whole units.');
            }
            if ($reservation->exists) {
                if (! app(CommerceLifecycleMutation::class)->active() || array_diff(array_keys($reservation->getDirty()), ['status', 'released_at', 'consumed_at', 'updated_at']) !== [] || $reservation->getOriginal('status') !== ReservationStatus::Active || ! in_array($reservation->status, [ReservationStatus::Consumed, ReservationStatus::Released], true)) {
                    throw new \LogicException('Reservation transition requires controlled order lifecycle.');
                }
            }
        });
        self::deleting(fn () => throw new \LogicException('Reservations cannot be deleted.'));
    }
}
