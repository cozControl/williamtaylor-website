<?php

namespace App\Domain\Checkout;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Checkout\Enums\OrderStatus;
use App\Domain\Checkout\Models\Order;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Snippe\SessionEvidence;
use App\Domain\Payments\Snippe\SnippeClient;
use App\Domain\Payments\Snippe\SnippeException;
use App\Domain\Payments\Snippe\SnippePaymentLifecycle;
use App\Domain\Payments\Support\CommerceLifecycleMutation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CancelUnpaidOrderService
{
    public const PAID = 'This order has been paid and cannot be cancelled through this action.';

    public const REVIEW = 'Payment status requires review before this order can be cancelled.';

    public const UNKNOWN = 'Cancellation could not be confirmed with the payment provider. The order has not been cancelled yet.';

    /** Read-only eligibility; handle() repeats it under canonical locks.
     * @param  Collection<int, Payment>  $payments
     */
    public function restriction(Order $order, Collection $payments): ?string
    {
        if ($order->payment_status === 'paid' || $order->status === OrderStatus::Confirmed || $payments->contains(fn (Payment $p) => $p->status === PaymentStatus::Completed)) {
            return self::PAID;
        }
        if ($order->status === OrderStatus::Cancelled) {
            return 'This order is already cancelled.';
        }
        if ($order->status !== OrderStatus::PendingConfirmation || $order->payment_status !== 'unpaid' || $order->fulfillment_status !== 'unfulfilled') {
            return 'This order is not eligible for unpaid cancellation.';
        }
        foreach ($payments as $payment) {
            if ($payment->method === 'mobile_money' && ($payment->active_order_id !== null || $payment->reconciliation_issue !== null)) {
                return 'Mobile Money must reach a verified final unpaid state before stock can be released.';
            }
            if ($payment->provider !== 'snippe') {
                return self::REVIEW;
            }
            if ($payment->active_order_id !== null) {
                if ($payment->active_order_id !== $order->id || $payment->provider_session_reference === null || ! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Processing, PaymentStatus::Failed], true)) {
                    return self::REVIEW;
                }
                if ($payment->io_lease_until?->isFuture()) {
                    return 'A payment operation is already running. Please check again shortly.';
                }
            } elseif (! ($payment->status === PaymentStatus::Failed && $payment->provider_session_reference === null && $payment->reconciliation_issue === null && in_array($payment->failure_code, ['http_400', 'http_401', 'http_403', 'http_404', 'configuration', 'https_required'], true))) {
                // Missing reference alone is never proof of no payable remote Session.
                return self::REVIEW;
            }
        }
        $lines = $order->lines;
        $main = StockLocation::query()->where('code', 'MAIN')->value('id');
        $reservations = InventoryReservation::query()->where('order_id', $order->id)->get()->keyBy('order_line_id');
        if ($lines->isEmpty() || $lines->count() !== $reservations->count() || InventoryMovement::query()->where('source_type', 'commerce_order')->where('source_id', $order->id)->where('type', 'order_issue')->exists()) {
            return 'Stock commitment requires review before this order can be cancelled.';
        }
        foreach ($lines as $line) {
            $reservation = $reservations->get($line->id);
            if ($reservation === null || $reservation->status->value !== 'active' || $reservation->quantity !== $line->quantity || $reservation->variant_id !== $line->variant_id || $reservation->stock_location_id !== $main) {
                return 'Stock commitment requires review before this order can be cancelled.';
            }
        }

        return null;
    }

    public function handle(User $actor, Order $order, string $reason): string
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::ORDERS_VIEW);
        Gate::forUser($actor)->authorize(PermissionRegistry::ORDERS_CANCEL);
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['cancellation_reason' => 'Provide a concise cancellation reason (up to 500 characters).']);
        }
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Cancellation must start outside a database transaction.');
        }
        $claim = DB::transaction(function () use ($actor, $order, $reason): Payment|string {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $payments = $order->payments()->orderBy('id')->lockForUpdate()->get();
            if ($restriction = $this->restriction($order, $payments)) {
                return $restriction;
            }
            $reservations = $this->lockedReservations($order);
            $payment = $payments->first(fn (Payment $p) => $p->active_order_id !== null);
            if ($payment === null) {
                return $this->finish($actor, $order, $reason, $reservations, null);
            }
            if (! config('snippe.enabled') || blank(config('snippe.api_key'))) {
                return 'Snippe must be enabled and configured before this payment Session can be cancelled.';
            }
            if ($payment->reconciliation_issue !== null && $payment->next_reconcile_at?->isFuture()) {
                return 'Payment verification is waiting for its next permitted check. Please try again later.';
            }
            $payment->update(['io_lease_until' => now('UTC')->addMinutes(5)]);

            return $payment;
        }, 3);
        if (is_string($claim)) {
            return $claim;
        }
        $lease = $claim->io_lease_until;
        try {
            $client = app(SnippeClient::class);
            // Read first: retries discover finality before another deliberate cancellation POST.
            $remote = $client->get($claim->provider_session_reference);
            if (in_array($remote->status, ['pending', 'active'], true)) {
                // Apply authenticated evidence first so mismatched identities cannot trigger a cancel request.
                $outcome = app(SnippePaymentLifecycle::class)->apply($claim, $remote->reference, $remote->status, $remote->amount, $remote->currency, $remote->metadata);
                if ($outcome !== 'processed' || $claim->fresh()->active_order_id === null) {
                    return $order->fresh()->payment_status === 'paid' ? self::PAID : self::REVIEW;
                }
                try {
                    $client->cancel($claim->provider_session_reference, 'wc-'.substr(hash('sha256', $claim->id), 0, 24));
                } catch (SnippeException) {
                    // Even rejection/timeout can race with completion. Only authenticated GET settles truth.
                }
                $remote = $client->get($claim->provider_session_reference);
            }

            return $this->settle($actor, $order, $claim, $remote, $reason);
        } catch (SnippeException $error) {
            DB::transaction(function () use ($claim, $error): void {
                Order::query()->lockForUpdate()->findOrFail($claim->order_id);
                $payment = Payment::query()->lockForUpdate()->findOrFail($claim->id);
                if ($payment->active_order_id !== null) {
                    $payment->update(['reconciliation_issue' => 'cancellation_unconfirmed', 'next_reconcile_at' => now('UTC')->addSeconds($error->retryAfter)]);
                }
            });

            return self::UNKNOWN;
        } finally {
            // Do not clear a newer operation's lease if this worker was delayed beyond its own lease.
            Payment::query()->whereKey($claim->id)->where('io_lease_until', $lease)->update(['io_lease_until' => null]);
        }
    }

    private function settle(User $actor, Order $order, Payment $payment, SessionEvidence $remote, string $reason): string
    {
        return DB::transaction(function () use ($actor, $order, $payment, $remote, $reason): string {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $payments = $order->payments()->orderBy('id')->lockForUpdate()->get();
            $payment = $payments->find($payment->id);
            if ($order->status === OrderStatus::Cancelled || $order->payment_status === 'paid') {
                return $order->payment_status === 'paid' ? self::PAID : 'This order is already cancelled.';
            }
            if ($order->status !== OrderStatus::PendingConfirmation || $order->fulfillment_status !== 'unfulfilled' || $order->payment_status !== 'unpaid' || $payments->contains(fn (Payment $p) => $p->status === PaymentStatus::Completed)) {
                return self::REVIEW;
            }
            $reservations = $this->lockedReservations($order);
            $outcome = app(SnippePaymentLifecycle::class)->apply($payment, $remote->reference, $remote->status, $remote->amount, $remote->currency, $remote->metadata);
            if ($outcome !== 'processed') {
                return self::REVIEW;
            }
            if ($remote->status === 'completed') {
                return self::PAID;
            }
            if (! in_array($remote->status, ['cancelled', 'expired'], true)) {
                $payment->refresh()->update(['reconciliation_issue' => 'cancellation_unconfirmed', 'next_reconcile_at' => now('UTC')->addMinutes(5)]);

                return self::UNKNOWN;
            }

            // Payment lifecycle has released the exact reservations in this same outer transaction.
            return $this->finish($actor, $order->refresh(), $reason, $reservations, $remote->status);
        }, 3);
    }

    /** @return Collection<int, InventoryReservation> */
    private function lockedReservations(Order $order): Collection
    {
        $lines = $order->lines()->get();
        Product::query()->whereIn('id', $lines->pluck('product_id'))->orderBy('id')->lockForUpdate()->get();
        ProductVariant::query()->whereIn('id', $lines->pluck('variant_id'))->orderBy('id')->lockForUpdate()->get();
        $location = StockLocation::query()->where('code', 'MAIN')->sharedLock()->firstOrFail();
        InventoryBalance::query()->where('stock_location_id', $location->id)->whereIn('variant_id', $lines->pluck('variant_id'))->orderBy('variant_id')->lockForUpdate()->get();
        $reservations = InventoryReservation::query()->where('order_id', $order->id)->orderBy('variant_id')->lockForUpdate()->get();
        $byLine = $reservations->keyBy('order_line_id');
        $valid = $lines->isNotEmpty() && $lines->count() === $reservations->count();
        foreach ($lines as $line) {
            $reservation = $byLine->get($line->id);
            $valid = $valid && $reservation !== null && $reservation->status->value === 'active' && $reservation->quantity === $line->quantity && $reservation->variant_id === $line->variant_id && $reservation->stock_location_id === $location->id;
        }
        if (! $valid || InventoryMovement::query()->where('source_type', 'commerce_order')->where('source_id', $order->id)->where('type', 'order_issue')->exists()) {
            throw ValidationException::withMessages(['cancellation_reason' => 'Stock commitment requires review before this order can be cancelled.']);
        }

        return $reservations;
    }

    /** @param Collection<int, InventoryReservation> $reservations */
    private function finish(User $actor, Order $order, string $reason, Collection $reservations, ?string $providerState): string
    {
        return app(CommerceLifecycleMutation::class)->run(function () use ($actor, $order, $reason, $reservations, $providerState): string {
            if ($providerState === null) {
                foreach ($reservations as $reservation) {
                    $reservation->update(['status' => 'released', 'released_at' => now('UTC')]);
                }
            }
            $order->update(['status' => OrderStatus::Cancelled, 'closed_at' => now('UTC'), 'cancelled_at' => now('UTC'), 'cancelled_by' => $actor->id, 'cancellation_reason' => $reason]);
            $quantity = $reservations->sum('quantity');
            // Internal free text stays on the Order; no customer text in generic audit metadata.
            app(RecordAuditEvent::class)->handle('commerce.order.cancelled', $order, $actor, null, ['order_number' => $order->order_number, 'quantity_released' => $quantity, 'provider_final_state' => $providerState ?? 'no_remote_session'], PermissionRegistry::ORDERS_CANCEL);
            request()->attributes->remove('inventory.storefront');
            request()->attributes->remove('cart.view');

            return 'Order '.$order->order_number.' was cancelled and '.$quantity.' reserved units were released.';
        });
    }
}
