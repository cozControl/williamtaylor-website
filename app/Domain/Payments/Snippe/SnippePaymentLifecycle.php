<?php

namespace App\Domain\Payments\Snippe;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Checkout\Enums\OrderStatus;
use App\Domain\Checkout\Models\Order;
use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Services\InventoryLedgerService;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\MobileMoneyPayment;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Support\CommerceLifecycleMutation;
use Illuminate\Support\Facades\DB;

final class SnippePaymentLifecycle
{
    /** Call only with authenticated provider evidence, never browser parameters.
     * @param  array<string, mixed>  $metadata
     */
    public function apply(Payment $payment, string $sessionReference, string $state, int $amount, string $currency, array $metadata = [], ?string $paymentReference = null): string
    {
        return DB::transaction(function () use ($payment, $sessionReference, $state, $amount, $currency, $metadata, $paymentReference) {
            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $direct = $payment->method === 'mobile_money';
            $expected = SnippeMoney::tzs($order->total_minor, $order->currency);
            $mismatch = $payment->provider !== 'snippe' || ($direct ? $payment->provider_payment_reference : $payment->provider_session_reference) !== $sessionReference || $amount !== $expected || $amount !== $payment->provider_amount_tzs || $currency !== 'TZS' || $payment->currency !== $currency || $payment->expected_amount_internal_minor !== $order->total_minor;
            foreach (['order_id' => $order->id, 'order_number' => $order->order_number, 'payment_attempt' => $payment->attempt_key, 'source' => 'william_taylor_web'] as $key => $value) {
                if (isset($metadata[$key]) && $metadata[$key] !== $value) {
                    $mismatch = true;
                }
            }
            if ($state === 'completed' && $paymentReference !== null && ($payment->provider_payment_reference !== null && $payment->provider_payment_reference !== $paymentReference || Payment::query()->where('provider_payment_reference', $paymentReference)->whereKeyNot($payment->id)->exists())) {
                $mismatch = true;
            }
            if ($direct && in_array($payment->reconciliation_issue, MobileMoneyPayment::REVIEW_REASONS, true)) {
                return $payment->reconciliation_issue;
            }
            if ($direct) {
                $payment->update(['last_verified_at' => now('UTC'), 'last_evidence' => ['reference' => $sessionReference, 'status' => $state, 'amount_tzs' => $amount, 'currency' => $currency]]);
            }
            if ($mismatch) {
                return $this->attention($payment, 'evidence_mismatch');
            }
            if ($payment->status === PaymentStatus::Completed) {
                // Late failed/pending/final-unpaid evidence cannot reverse a paid order.
                if ($state === 'completed' && $payment->provider_payment_reference === null && $paymentReference !== null) {
                    $payment->update(['provider_payment_reference' => $paymentReference]);
                }

                return 'processed';
            }
            if ($order->status !== OrderStatus::PendingConfirmation || $order->payment_status !== 'unpaid' || $payment->active_order_id !== $order->id) {
                if (in_array($state, ['expired', 'cancelled', 'voided', 'failed'], true) && in_array($payment->status, [PaymentStatus::Expired, PaymentStatus::Cancelled, PaymentStatus::Voided, PaymentStatus::Failed], true)) {
                    return 'processed';
                }

                return $this->attention($payment, 'incompatible_lifecycle');
            }
            if (in_array($state, $direct ? ['pending'] : ['pending', 'active', 'failed'], true)) {
                $payment->update(['status' => match ($state) {
                    'pending' => PaymentStatus::Pending, 'active' => PaymentStatus::Processing, default => PaymentStatus::Failed
                }, 'reconciliation_issue' => null, 'last_provider_status' => $state, 'failed_at' => $state === 'failed' ? now('UTC') : $payment->failed_at, 'last_failure_reference' => $state === 'failed' ? $paymentReference : $payment->last_failure_reference, 'failure_code' => $state === 'failed' ? 'payment_attempt_failed' : null, 'next_reconcile_at' => now('UTC')->addMinutes(5)]);

                return 'processed';
            }
            if (! in_array($state, $direct ? ['completed', 'expired', 'voided', 'failed'] : ['completed', 'expired', 'cancelled'], true)) {
                return $this->attention($payment, 'unknown_provider_state');
            }
            $lines = $order->lines()->get();
            Product::query()->whereIn('id', $lines->pluck('product_id'))->orderBy('id')->lockForUpdate()->get();
            ProductVariant::query()->whereIn('id', $lines->pluck('variant_id'))->orderBy('id')->lockForUpdate()->get();
            $location = StockLocation::query()->where('code', 'MAIN')->sharedLock()->firstOrFail();
            InventoryBalance::query()->where('stock_location_id', $location->id)->whereIn('variant_id', $lines->pluck('variant_id'))->orderBy('variant_id')->lockForUpdate()->get();
            $reservations = InventoryReservation::query()->where('order_id', $order->id)->orderBy('variant_id')->lockForUpdate()->get()->keyBy('order_line_id');
            if ($lines->isEmpty() || $reservations->count() !== $lines->count()) {
                return $this->attention($payment, 'reservation_mismatch');
            }
            foreach ($lines as $line) {
                $reservation = $reservations->get($line->id);
                if (! $reservation || $reservation->variant_id !== $line->variant_id || $reservation->stock_location_id !== $location->id || $reservation->quantity !== $line->quantity || $reservation->status !== ReservationStatus::Active) {
                    return $this->attention($payment, 'reservation_mismatch');
                }
            }

            return app(CommerceLifecycleMutation::class)->run(function () use ($state, $payment, $order, $reservations, $paymentReference) {
                $paid = $state === 'completed';
                $payment->update(['status' => $paid ? PaymentStatus::Completed : match ($state) {
                    'expired' => PaymentStatus::Expired, 'voided' => PaymentStatus::Voided, 'failed' => PaymentStatus::Failed, default => PaymentStatus::Cancelled
                }, 'last_provider_status' => $state, 'provider_payment_reference' => $paymentReference ?? $payment->provider_payment_reference, 'completed_at' => $paid ? now('UTC') : null, 'expired_at' => $state === 'expired' ? now('UTC') : null, 'failed_at' => $state === 'failed' ? now('UTC') : $payment->failed_at, 'active_order_id' => null, 'failure_code' => null, 'reconciliation_issue' => null, 'next_reconcile_at' => null]);
                $order->update(['status' => $paid ? OrderStatus::Confirmed : ($state === 'expired' ? OrderStatus::PaymentExpired : OrderStatus::Cancelled), 'payment_status' => $paid ? 'paid' : 'unpaid', 'confirmed_at' => $paid ? now('UTC') : null, 'closed_at' => $paid ? null : now('UTC')]);
                foreach ($reservations as $reservation) {
                    if ($paid) {
                        app(InventoryLedgerService::class)->issueReserved($payment, $reservation);
                    } else {
                        $reservation->update(['status' => ReservationStatus::Released, 'released_at' => now('UTC')]);
                    }
                }
                app(RecordAuditEvent::class)->handle('commerce.payment.'.$state, $payment, null, null, ['order_number' => $order->order_number, 'amount_tzs' => $payment->provider_amount_tzs, 'currency' => 'TZS', 'reservation_outcome' => $paid ? 'consumed' : 'released']);
                request()->attributes->remove('inventory.storefront');
                request()->attributes->remove('cart.view');

                return 'processed';
            });
        }, 3);
    }

    private function attention(Payment $payment, string $reason): string
    {
        $payment->update(['status' => $payment->method === 'mobile_money' && $payment->status !== PaymentStatus::Completed ? PaymentStatus::AttentionRequired : $payment->status, 'reconciliation_issue' => $reason, 'next_reconcile_at' => now('UTC')->addMinutes(5)]);
        app(RecordAuditEvent::class)->handle('commerce.payment.needs_attention', $payment, null, null, ['reason' => $reason]);

        return $reason;
    }
}
