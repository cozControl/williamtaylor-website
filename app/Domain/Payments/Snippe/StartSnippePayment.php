<?php

namespace App\Domain\Payments\Snippe;

use App\Domain\Checkout\Enums\OrderStatus;
use App\Domain\Checkout\Models\Order;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\MobileMoneyPayment;
use App\Domain\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;

final class StartSnippePayment
{
    public function start(Order $order): Payment
    {
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Start payment only after the order commits.');
        }
        $payment = DB::transaction(function () use ($order) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $existing = Payment::query()->where('active_order_id', $order->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }
            if ($order->status !== OrderStatus::PendingConfirmation || $order->payment_status !== 'unpaid') {
                return Payment::query()->where('order_id', $order->id)->latest('id')->firstOrFail();
            }
            $lines = $order->lines()->get();
            $reserved = InventoryReservation::query()->where('order_id', $order->id)->where('status', 'active')->get()->keyBy('order_line_id');
            if ($lines->isEmpty() || $lines->count() !== $reserved->count()) {
                throw new SnippeException('reservation_mismatch', false);
            }
            foreach ($lines as $line) {
                $reservation = $reserved->get($line->id);
                if (! $reservation || $reservation->quantity !== $line->quantity || $reservation->variant_id !== $line->variant_id) {
                    throw new SnippeException('reservation_mismatch', false);
                }
            }

            return Payment::query()->create(['order_id' => $order->id, 'active_order_id' => $order->id, 'provider' => 'snippe', 'status' => PaymentStatus::Pending, 'currency' => $order->currency, 'expected_amount_internal_minor' => $order->total_minor, 'provider_amount_tzs' => SnippeMoney::tzs($order->total_minor, $order->currency), 'attempt_key' => 'wt-'.bin2hex(random_bytes(12)), 'return_reference' => bin2hex(random_bytes(32))]);
        });

        return $this->refresh($payment);
    }

    public function refresh(Payment $payment): Payment
    {
        if ($payment->method === 'mobile_money') {
            return app(MobileMoneyPayment::class)->refresh($payment);
        }
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Reconciliation must not hold database locks across provider calls.');
        }
        $claimed = DB::transaction(function () use ($payment) {
            Order::query()->lockForUpdate()->findOrFail($payment->order_id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->active_order_id === null || $payment->io_lease_until?->isFuture() || $payment->next_reconcile_at?->isFuture()) {
                return false;
            }
            $payment->update(['io_lease_until' => now('UTC')->addMinutes(5)]);

            return true;
        });
        if (! $claimed) {
            return $payment->fresh();
        }
        $operation = 'reconcile';
        try {
            $payment->refresh();
            $client = app(SnippeClient::class);
            if ($payment->provider_session_reference !== null) {
                $remote = $client->get($payment->provider_session_reference);
            } elseif ($payment->request_started_at !== null) {
                $remote = $client->findAttempt($payment->attempt_key);
                if ($remote === null) {
                    throw new SnippeException('session_outcome_unknown');
                }
            } else {
                $operation = 'prepare';
                $payload = $this->payload($payment);
                $payment->update(['request_started_at' => now('UTC')]);
                $operation = 'create';
                $remote = $client->create($payload, $payment->attempt_key);
            }
            $bound = DB::transaction(function () use ($payment, $remote) {
                $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);
                $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                $mismatch = $remote->amount !== $payment->provider_amount_tzs || $remote->currency !== $payment->currency || ($payment->provider_session_reference !== null && $payment->provider_session_reference !== $remote->reference) || Payment::query()->where('provider_session_reference', $remote->reference)->whereKeyNot($payment->id)->exists();
                foreach (['order_id' => $order->id, 'order_number' => $order->order_number, 'source' => 'william_taylor_web', 'payment_attempt' => $payment->attempt_key] as $key => $value) {
                    if (isset($remote->metadata[$key]) && $remote->metadata[$key] !== $value) {
                        $mismatch = true;
                    }
                }
                if ($mismatch) {
                    $payment->update(['reconciliation_issue' => 'session_mismatch', 'next_reconcile_at' => now('UTC')->addMinutes(5)]);

                    return false;
                }
                $payment->update(['provider_session_reference' => $remote->reference, 'provider_checkout_url' => $remote->checkoutUrl, 'expires_at' => $remote->expiresAt, 'failure_code' => null, 'reconciliation_issue' => null]);

                return true;
            });
            if ($bound) {
                app(SnippePaymentLifecycle::class)->apply($payment->fresh(), $remote->reference, $remote->status, $remote->amount, $remote->currency, $remote->metadata);
            }
        } catch (SnippeException $error) {
            DB::transaction(function () use ($payment, $error, $operation) {
                Order::query()->lockForUpdate()->findOrFail($payment->order_id);
                $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                if ($payment->active_order_id !== null) {
                    // A known session is never released by an HTTP/network failure.
                    $definite = ! $error->ambiguous && $payment->provider_session_reference === null && in_array($operation, ['prepare', 'create'], true);
                    $payment->update(['status' => $definite ? PaymentStatus::Failed : PaymentStatus::Processing, 'failure_code' => $error->reason, 'reconciliation_issue' => $definite ? null : $error->reason, 'active_order_id' => $definite ? null : $payment->active_order_id, 'failed_at' => $definite ? now('UTC') : null, 'next_reconcile_at' => now('UTC')->addSeconds($error->retryAfter)]);
                }
            });
        } finally {
            Payment::query()->whereKey($payment->id)->update(['io_lease_until' => null]);
        }

        return $payment->fresh();
    }

    /** @return array<string, mixed> */
    private function payload(Payment $payment): array
    {
        $order = Order::query()->with('lines')->findOrFail($payment->order_id);
        $expiry = config('snippe.session_expires_in');
        if (! config('snippe.enabled') || blank(config('snippe.api_key')) || blank(config('snippe.profile_id')) || blank(config('snippe.webhook_secret')) || ! is_int($expiry) || $expiry < 60 || $expiry > 86400 || $order->lines->count() > 50) {
            throw new SnippeException('configuration', false);
        }
        $origin = rtrim((string) config('app.url'), '/');
        if (app()->environment('production') && parse_url($origin, PHP_URL_SCHEME) !== 'https') {
            throw new SnippeException('https_required', false);
        }

        return ['profile_id' => config('snippe.profile_id'), 'amount' => SnippeMoney::tzs($order->total_minor, $order->currency), 'currency' => 'TZS', 'allow_custom_amount' => false, 'customer' => $order->customer_snapshot, 'metadata' => ['order_id' => $order->id, 'order_number' => $order->order_number, 'source' => 'william_taylor_web', 'payment_attempt' => $payment->attempt_key], 'description' => 'William Taylor Order '.$order->order_number, 'redirect_url' => $origin.route('snippe.return', $payment->return_reference, false), 'webhook_url' => $origin.route('snippe.webhook', [], false), 'expires_in' => $expiry, 'line_items' => $order->lines->map(fn ($line) => ['id' => $line->id, 'name' => $line->product_title_snapshot, 'description' => implode(' / ', $line->options_snapshot), 'quantity' => $line->quantity, 'unit_price' => SnippeMoney::tzs($line->unit_price_minor, $order->currency, false), 'sku' => $line->sku_snapshot])->all(), 'display' => ['show_line_items' => true, 'line_items_style' => 'compact']];
    }
}
