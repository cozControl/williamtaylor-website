<?php

namespace App\Domain\Payments;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Checkout\Enums\OrderStatus;
use App\Domain\Checkout\Models\Order;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Snippe\SnippeException;
use App\Domain\Payments\Snippe\SnippeMoney;
use App\Domain\Payments\Snippe\SnippePaymentLifecycle;
use App\Domain\Payments\Support\PaymentEvidence;
use App\Domain\Payments\Support\TanzanianPhone;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class MobileMoneyPayment
{
    public const REVIEW_REASONS = ['evidence_mismatch', 'reservation_mismatch', 'incompatible_lifecycle', 'webhook_mismatch', 'idempotency_window_elapsed'];

    // Called inside canonical Order placement, before its transaction commits.
    public function prepare(Order $order, string $phone): Payment
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Prepare the payment within the Order transaction.');
        }
        $phone = TanzanianPhone::normalize($phone);
        $names = preg_split('/\s+/', trim($order->customer_snapshot['name']), 2);
        if (! is_array($names) || count($names) < 2) {
            throw ValidationException::withMessages(['name' => 'Enter your first and last name for Mobile Money payment.']);
        }
        $key = 'wt-'.bin2hex(random_bytes(12));
        $amount = SnippeMoney::tzs($order->total_minor, $order->currency);
        $body = [
            'payment_type' => 'mobile', 'details' => ['amount' => $amount, 'currency' => $order->currency],
            'phone_number' => $phone,
            'customer' => ['firstname' => $names[0], 'lastname' => $names[1], 'email' => $order->customer_snapshot['email']],
            'webhook_url' => rtrim((string) config('app.url'), '/').route('snippe.webhook', [], false),
            'metadata' => ['order_id' => $order->id, 'order_number' => $order->order_number, 'payment_attempt' => $key],
        ];

        return Payment::query()->create([
            'order_id' => $order->id, 'active_order_id' => $order->id, 'provider' => 'snippe', 'method' => 'mobile_money',
            'status' => PaymentStatus::Created, 'currency' => $order->currency,
            'expected_amount_internal_minor' => $order->total_minor, 'provider_amount_tzs' => $amount,
            'attempt_key' => $key, 'return_reference' => bin2hex(random_bytes(32)),
            'customer_phone' => $phone, 'request_snapshot' => $body,
        ]);
    }

    public function start(Order $order): Payment
    {
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Initiate only after Order commit.');
        }
        $payment = DB::transaction(function () use ($order) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $payments = $order->payments()->orderByDesc('id')->lockForUpdate()->get();
            if ($active = $payments->firstWhere('active_order_id', $order->id)) {
                return $active;
            }
            $previous = $payments->first();
            if ($order->status !== OrderStatus::PendingConfirmation || $order->payment_status !== 'unpaid' || ! $previous?->retrySafe()) {
                throw new SnippeException('retry_not_safe', false);
            }
            $lines = $order->lines()->get();
            $reservations = InventoryReservation::query()->where('order_id', $order->id)->where('status', 'active')->lockForUpdate()->get()->keyBy('order_line_id');
            if ($lines->isEmpty() || $lines->count() !== $reservations->count()) {
                throw new SnippeException('reservation_mismatch', false);
            }
            foreach ($lines as $line) {
                $reservation = $reservations->get($line->id);
                if (! $reservation || $reservation->variant_id !== $line->variant_id || $reservation->quantity !== $line->quantity) {
                    throw new SnippeException('reservation_mismatch', false);
                }
            }

            return $this->prepare($order, $previous->customer_phone);
        });

        return $this->refresh($payment);
    }

    /** Intentional initiation/replay boundary; never use for unattended reconciliation. */
    public function refresh(Payment $payment, bool $webhook = false): Payment
    {
        return $this->refreshPayment($payment, $webhook, allowInitiation: true);
    }

    /** Provider GET only. Missing references are retained for investigation. */
    public function reconcile(Payment $payment, bool $webhook = false): Payment
    {
        return $this->refreshPayment($payment, $webhook, allowInitiation: false);
    }

    public function recoverInitiation(Payment $payment, User $actor, string $reason): Payment
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::ORDERS_VIEW);
        Gate::forUser($actor)->authorize(PermissionRegistry::ORDERS_PAYMENT_STATUS_MANAGE);
        if (trim($reason) === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => 'Provide a recovery reason of at most 500 characters.']);
        }

        return $this->refreshPayment($payment, false, true, $actor, trim($reason));
    }

    private function refreshPayment(Payment $payment, bool $webhook, bool $allowInitiation, ?User $recoveryActor = null, ?string $recoveryReason = null): Payment
    {
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Provider I/O cannot hold database transactions.');
        }
        $lease = bin2hex(random_bytes(16));
        // A unique lease token prevents an old worker from releasing a newer worker's lease.
        $claimed = DB::transaction(function () use ($payment, $webhook, $lease, $recoveryActor, $recoveryReason) {
            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);
            $current = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($current->provider !== 'snippe' || $current->method !== 'mobile_money' || $current->active_order_id === null || $current->io_lease_until?->isFuture() || ((! $webhook || $current->failure_code === 'rate_limited') && $current->next_reconcile_at?->isFuture()) || in_array($current->reconciliation_issue, self::REVIEW_REASONS, true)) {
                if ($recoveryActor !== null) {
                    throw new SnippeException('recovery_not_eligible_or_deferred', false);
                }

                return false;
            }
            if ($recoveryActor !== null) {
                if ($order->status !== OrderStatus::PendingConfirmation || $order->payment_status !== 'unpaid'
                    || $current->provider_payment_reference !== null || $current->provider_session_reference !== null
                    || ! in_array($current->status, [PaymentStatus::Created, PaymentStatus::Initiating, PaymentStatus::AttentionRequired], true)) {
                    throw new SnippeException('recovery_not_eligible', false);
                }
                $lines = $order->lines()->get();
                $reservations = InventoryReservation::query()->where('order_id', $order->id)->where('status', 'active')->lockForUpdate()->get()->keyBy('order_line_id');
                if ($lines->isEmpty() || $lines->count() !== $reservations->count()) {
                    throw new SnippeException('reservation_mismatch', false);
                }
                foreach ($lines as $line) {
                    $reservation = $reservations->get($line->id);
                    if (! $reservation || $reservation->variant_id !== $line->variant_id || $reservation->quantity !== $line->quantity) {
                        throw new SnippeException('reservation_mismatch', false);
                    }
                }
                app(RecordAuditEvent::class)->handle('commerce.payment.initiation_recovery_requested', $current, $recoveryActor,
                    after: ['order_number' => $order->order_number, 'existing_attempt' => true],
                    permission: PermissionRegistry::ORDERS_PAYMENT_STATUS_MANAGE, reason: $recoveryReason);
            }
            $current->update(['io_lease_until' => now('UTC')->addMinutes(2), 'io_lease_token' => $lease]);

            return true;
        });
        if (! $claimed) {
            return $payment->fresh();
        }
        $firstSend = false;
        try {
            $payment->refresh();
            $gateway = app(PaymentGateway::class);
            if ($payment->provider_payment_reference !== null) {
                $remote = $gateway->status($payment->provider_payment_reference);
            } else {
                // A one-hour safety margin avoids replaying across provider key expiry.
                if ($payment->request_started_at?->lessThanOrEqualTo(now('UTC')->subHours(23))) {
                    throw new SnippeException('idempotency_window_elapsed');
                }
                if (! $allowInitiation) {
                    throw new SnippeException($payment->request_started_at === null ? 'initiation_not_recorded' : 'initiation_outcome_unknown', retryAfter: 300);
                }
                $firstSend = $payment->request_started_at === null;
                $body = $payment->request_snapshot;
                if (! config('snippe.enabled') || blank(config('snippe.api_key')) || blank(config('snippe.webhook_secret'))) {
                    throw new SnippeException('configuration', false);
                }
                if (parse_url($body['webhook_url'], PHP_URL_SCHEME) !== 'https' || strlen($body['webhook_url']) > 500) {
                    throw new SnippeException('https_required', false);
                }
                $payment->update(['request_started_at' => $payment->request_started_at ?? now('UTC'), 'status' => PaymentStatus::Initiating]);
                $remote = $gateway->initiate($body, $payment->attempt_key);
                if (! $this->bind($payment, $remote)) {
                    return $payment->fresh();
                }
                // Even an immediate completed POST response must be verified by GET.
                if ($remote->status !== 'pending') {
                    $remote = $gateway->status($remote->reference);
                } else {
                    DB::transaction(function () use ($payment) {
                        Order::query()->lockForUpdate()->findOrFail($payment->order_id);
                        $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                        if ($payment->active_order_id !== null && ! in_array($payment->reconciliation_issue, self::REVIEW_REASONS, true)) {
                            $payment->update(['status' => PaymentStatus::Pending, 'last_provider_status' => 'pending', 'failure_code' => null, 'reconciliation_issue' => null, 'next_reconcile_at' => now('UTC')->addMinute()]);
                        }
                    });

                    return $payment->fresh();
                }
            }
            app(SnippePaymentLifecycle::class)->apply($payment->fresh(), $remote->reference, $remote->status, $remote->amount, $remote->currency, $remote->metadata, $remote->reference);
        } catch (UniqueConstraintViolationException) {
            DB::transaction(function () use ($payment) {
                Order::query()->lockForUpdate()->findOrFail($payment->order_id);
                $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                if ($payment->active_order_id !== null) {
                    $payment->update(['status' => PaymentStatus::AttentionRequired, 'reconciliation_issue' => 'evidence_mismatch']);
                }
            });
        } catch (SnippeException $error) {
            DB::transaction(function () use ($payment, $error, $firstSend) {
                Order::query()->lockForUpdate()->findOrFail($payment->order_id);
                $current = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                if ($current->active_order_id === null || in_array($current->reconciliation_issue, self::REVIEW_REASONS, true)) {
                    return;
                }
                // A rejection during replay never proves the original request failed.
                $definite = $firstSend && ! $error->ambiguous && $current->provider_payment_reference === null;
                $classificationOnly = in_array($error->reason, ['initiation_not_recorded', 'initiation_outcome_unknown'], true);
                $current->update(['status' => $definite ? PaymentStatus::Failed : PaymentStatus::AttentionRequired, 'failure_code' => $classificationOnly ? $current->failure_code : $error->reason, 'reconciliation_issue' => $definite ? null : $error->reason, 'active_order_id' => $definite ? null : $current->active_order_id, 'failed_at' => $definite ? now('UTC') : null, 'next_reconcile_at' => now('UTC')->addSeconds($error->retryAfter)]);
                Log::warning('Payment provider operation deferred.', ['payment_id' => $current->id, 'reason' => $error->reason]);
            });
        } finally {
            Payment::query()->whereKey($payment->id)->where('io_lease_token', $lease)->update(['io_lease_until' => null, 'io_lease_token' => null]);
        }

        return $payment->fresh();
    }

    private function bind(Payment $payment, PaymentEvidence $remote): bool
    {
        return DB::transaction(function () use ($payment, $remote) {
            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $payment->update(['last_evidence' => ['reference' => $remote->reference, 'status' => $remote->status, 'amount_tzs' => $remote->amount, 'currency' => $remote->currency]]);
            $mismatch = $remote->amount !== $payment->provider_amount_tzs || $remote->currency !== $order->currency || ($payment->provider_payment_reference !== null && $payment->provider_payment_reference !== $remote->reference) || Payment::query()->where('provider_payment_reference', $remote->reference)->whereKeyNot($payment->id)->exists();
            foreach (['order_id' => $order->id, 'order_number' => $order->order_number, 'payment_attempt' => $payment->attempt_key] as $key => $value) {
                $mismatch = $mismatch || (isset($remote->metadata[$key]) && $remote->metadata[$key] !== $value);
            }
            if ($mismatch) {
                $payment->update(['status' => PaymentStatus::AttentionRequired, 'reconciliation_issue' => 'evidence_mismatch']);

                return false;
            }
            $payment->update(['provider_payment_reference' => $remote->reference, 'expires_at' => $remote->expiresAt]);

            return true;
        });
    }
}
