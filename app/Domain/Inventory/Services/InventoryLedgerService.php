<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Support\CommerceLifecycleMutation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class InventoryLedgerService
{
    public const MAX_UNITS = 2147483647;

    public function post(User $actor, ProductVariant $variant, StockLocation $location, MovementType $type, mixed $quantity, string $reason, ?string $note = null, ?string $idempotencyKey = null, ?string $sourceType = null, ?string $sourceId = null): InventoryMovement
    {
        return $this->write($actor, $variant, $location, $type->value, $quantity, $reason, $note, $idempotencyKey, $sourceType, $sourceId, null);
    }

    public function count(User $actor, ProductVariant $variant, StockLocation $location, mixed $counted, mixed $expectedOnHand, string $reason, ?string $note = null, ?string $idempotencyKey = null): InventoryMovement
    {
        if (! is_int($expectedOnHand) || $expectedOnHand < 0 || $expectedOnHand > self::MAX_UNITS) {
            throw ValidationException::withMessages(['quantity' => 'Reload the current stock balance before adjusting.']);
        }

        return $this->write($actor, $variant, $location, 'count', $counted, $reason, $note, $idempotencyKey, null, null, $expectedOnHand);
    }

    private function write(User $actor, ProductVariant $variant, StockLocation $location, string $operation, mixed $quantity, string $reason, ?string $note, ?string $key, ?string $sourceType, ?string $sourceId, ?int $expected): InventoryMovement
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::INVENTORY_MANAGE);
        if ($operation === MovementType::OrderIssue->value) {
            throw new \LogicException('Order issue requires verified payment and a canonical reservation.');
        }
        if (! is_int($quantity) || $quantity < ($operation === 'count' ? 0 : 1) || $quantity > self::MAX_UNITS) {
            throw ValidationException::withMessages(['quantity' => 'Enter a whole number of units within the supported range.']);
        }
        $reason = trim($reason);
        $note = filled($note) ? trim($note) : null;
        if ($reason === '' || mb_strlen($reason) > 255 || mb_strlen($note ?? '') > 2000 || ($key !== null && (trim($key) === '' || strlen($key) > 191)) || strlen($sourceType ?? '') > 100 || strlen($sourceId ?? '') > 191 || (($sourceType === null) !== ($sourceId === null))) {
            throw ValidationException::withMessages(['reason' => 'Provide a reason and valid reference details.']);
        }
        $fingerprint = hash('sha256', json_encode([$actor->id, $variant->id, $location->id, $operation, $quantity, $reason, $note, $sourceType, $sourceId, $expected], JSON_THROW_ON_ERROR));
        try {
            return DB::transaction(function () use ($actor, $variant, $location, $operation, $quantity, $reason, $note, $key, $sourceType, $sourceId, $expected, $fingerprint): InventoryMovement {
                // Parent locks also serialize first-balance creation and protect against archiving.
                $product = Product::query()->lockForUpdate()->findOrFail($variant->product_id);
                $lockedVariant = ProductVariant::query()->lockForUpdate()->findOrFail($variant->id);
                $lockedLocation = StockLocation::query()->sharedLock()->findOrFail($location->id);
                if ($key !== null && ($existing = InventoryMovement::query()->where('idempotency_key', $key)->lockForUpdate()->first())) {
                    return $this->replay($existing, $fingerprint);
                }
                if ($lockedVariant->product_id !== $product->id || $product->archived_at !== null || $lockedVariant->archived_at !== null || ! $lockedLocation->active) {
                    throw ValidationException::withMessages(['quantity' => 'Stock cannot be posted to an archived Product/Variant or inactive location.']);
                }
                $balance = InventoryBalance::query()->where('variant_id', $variant->id)->where('stock_location_id', $location->id)->lockForUpdate()->first();
                $onHand = $balance->on_hand ?? 0;
                if ($operation === MovementType::Opening->value && InventoryMovement::query()->where('variant_id', $variant->id)->where('stock_location_id', $location->id)->exists()) {
                    throw ValidationException::withMessages(['operation' => 'Opening Stock is only available before the first movement at this location. Use Receive Stock or an adjustment.']);
                }
                if ($operation === 'count' && $expected !== $onHand) {
                    throw ValidationException::withMessages(['quantity' => 'Stock changed since this form was opened. Reload and recount before saving.']);
                }
                $delta = $operation === 'count' ? $quantity - $onHand : ($operation === MovementType::AdjustmentOut->value ? -$quantity : $quantity);
                if ($delta === 0) {
                    throw ValidationException::withMessages(['quantity' => 'The counted quantity matches stock on hand. No adjustment is needed.']);
                }
                $after = $onHand + $delta;
                if ($after < 0) {
                    throw ValidationException::withMessages(['quantity' => "Only {$onHand} units are currently on hand at {$lockedLocation->name}."]);
                }
                if ($after > self::MAX_UNITS) {
                    throw ValidationException::withMessages(['quantity' => 'This receipt exceeds the supported stock balance.']);
                }
                if ($after < app(InventoryReservationService::class)->activeQuantity($variant->id, $location->id)) {
                    throw ValidationException::withMessages(['quantity' => 'Stock cannot be reduced below quantities committed to pending orders.']);
                }
                $type = $operation === 'count' ? ($delta > 0 ? MovementType::AdjustmentIn : MovementType::AdjustmentOut) : MovementType::from($operation);
                $movement = InventoryMovement::query()->create(['variant_id' => $variant->id, 'stock_location_id' => $location->id, 'type' => $type, 'quantity_delta' => $delta, 'balance_after' => $after, 'reason' => $reason, 'note' => $note, 'actor_id' => $actor->id, 'source_type' => $sourceType, 'source_id' => $sourceId, 'idempotency_key' => $key, 'request_fingerprint' => $fingerprint, 'occurred_at' => now('UTC'), 'created_at' => now('UTC')]);
                if ($balance === null) {
                    $balance = new InventoryBalance(['variant_id' => $variant->id, 'stock_location_id' => $location->id]);
                }
                $balance->forceFill(['on_hand' => $after])->save();
                app(RecordAuditEvent::class)->handle('inventory.'.$type->value, $movement, $actor, ['on_hand' => $onHand], ['on_hand' => $after, 'quantity_delta' => $delta], PermissionRegistry::INVENTORY_MANAGE, $reason);

                return $movement;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $key === null ? null : InventoryMovement::query()->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing === null) {
                throw $exception;
            }

            return $this->replay($existing, $fingerprint);
        }
    }

    private function replay(InventoryMovement $movement, string $fingerprint): InventoryMovement
    {
        if (! hash_equals($movement->request_fingerprint, $fingerprint)) {
            throw ValidationException::withMessages(['idempotency_key' => 'This submission was already used for different stock details. Reload the form.']);
        }

        return $movement;
    }

    public function issueReserved(Payment $payment, InventoryReservation $reservation): InventoryMovement
    {
        if (! app(CommerceLifecycleMutation::class)->active() || DB::transactionLevel() < 1 || $payment->status !== PaymentStatus::Completed || $reservation->order_id !== $payment->order_id) {
            throw new \LogicException('Verified payment lifecycle is required for stock issue.');
        }
        $key = 'order:'.$reservation->order_id.':line:'.$reservation->order_line_id.':issue';
        $existing = InventoryMovement::query()->where('idempotency_key', $key)->lockForUpdate()->first();
        if ($existing) {
            if ($existing->variant_id !== $reservation->variant_id || $existing->stock_location_id !== $reservation->stock_location_id || $existing->quantity_delta !== -$reservation->quantity || $existing->type !== MovementType::OrderIssue || $reservation->status !== ReservationStatus::Consumed) {
                throw new \LogicException('Conflicting order issue evidence.');
            }

            return $existing;
        }
        if ($reservation->status !== ReservationStatus::Active) {
            throw new \LogicException('Only active reservations can be issued.');
        }
        $balance = InventoryBalance::query()->where('variant_id', $reservation->variant_id)->where('stock_location_id', $reservation->stock_location_id)->lockForUpdate()->firstOrFail();
        $before = $balance->on_hand;
        $after = $before - $reservation->quantity;
        if ($after < 0) {
            throw new \LogicException('Reserved stock is missing.');
        }
        $reservation->update(['status' => ReservationStatus::Consumed, 'consumed_at' => now('UTC')]);
        if ($after < app(InventoryReservationService::class)->activeQuantity($reservation->variant_id, $reservation->stock_location_id)) {
            throw new \LogicException('Other stock commitments would be violated.');
        }
        $movement = InventoryMovement::query()->create(['variant_id' => $reservation->variant_id, 'stock_location_id' => $reservation->stock_location_id, 'type' => MovementType::OrderIssue, 'quantity_delta' => -$reservation->quantity, 'balance_after' => $after, 'reason' => 'Verified Snippe payment', 'actor_id' => null, 'source_type' => 'commerce_order', 'source_id' => $payment->order_id, 'idempotency_key' => $key, 'request_fingerprint' => hash('sha256', $key.':'.$reservation->quantity), 'occurred_at' => now('UTC'), 'created_at' => now('UTC')]);
        $balance->update(['on_hand' => $after]);
        app(RecordAuditEvent::class)->handle('inventory.order_issue', $movement, null, ['on_hand' => $before], ['on_hand' => $after, 'quantity_delta' => -$reservation->quantity, 'payment_id' => $payment->id]);

        return $movement;
    }
}
