<?php

namespace App\Domain\Payments\Snippe;

use App\Domain\Checkout\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\MobileMoneyPayment;
use App\Domain\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProcessSnippeWebhook
{
    /** @param array<string, mixed> $event */
    public function process(array $event, string $body): string
    {
        if (! isset($event['data']['session_reference'])) {
            return $this->direct($event, $body);
        }
        $type = $event['type'];
        $data = $event['data'];
        $hash = hash('sha256', $body);
        $key = hash('sha256', $event['id'] ?? $hash);

        return DB::transaction(function () use ($type, $data, $hash, $key) {
            DB::table('snippe_webhook_receipts')->insertOrIgnore(['id' => (string) Str::ulid(), 'event_key' => $key, 'body_hash' => $hash, 'type' => $type, 'outcome' => 'received', 'created_at' => now('UTC')]);
            $receipt = DB::table('snippe_webhook_receipts')->where('event_key', $key)->lockForUpdate()->first();
            if ($receipt->body_hash !== $hash) {
                return 'event_conflict';
            }
            if ($receipt->outcome === 'processed') {
                return 'processed';
            }
            $session = $data['session_reference'] ?? null;
            $reference = $data['reference'] ?? null;
            $amount = $data['amount']['value'] ?? null;
            $currency = $data['amount']['currency'] ?? null;
            $validReference = fn ($value) => is_string($value) && preg_match('/^[A-Za-z0-9_-]{1,191}$/D', $value);
            $outcome = 'ignored';
            if (in_array($type, ['payment.completed', 'payment.failed'], true)) {
                $state = $type === 'payment.completed' ? 'completed' : 'failed';
                if (! $validReference($session) || ! $validReference($reference) || ! is_int($amount) || ! is_string($currency) || ($data['status'] ?? null) !== $state || (isset($data['metadata']) && ! is_array($data['metadata']))) {
                    $outcome = 'malformed_event';
                } else {
                    $payment = Payment::query()->where('provider_session_reference', $session)->first();
                    $outcome = $payment ? app(SnippePaymentLifecycle::class)->apply($payment, $session, $state, $amount, $currency, $data['metadata'] ?? [], $reference) : 'unknown_session';
                }
            }
            DB::table('snippe_webhook_receipts')->where('event_key', $key)->update(['session_reference' => $validReference($session) ? $session : null, 'outcome' => $outcome]);

            return $outcome;
        }, 3);
    }

    /** @param array<string, mixed> $event */
    private function direct(array $event, string $body): string
    {
        $data = $event['data'];
        $reference = $data['reference'] ?? null;
        if (! is_string($event['id'] ?? null) || $event['id'] === '' || ! is_string($reference) || ! preg_match('/^[A-Za-z0-9_-]{1,191}$/D', $reference)) {
            return 'malformed_event';
        }
        $key = hash('sha256', $event['id']);
        $hash = hash('sha256', $body);
        // Commit a bounded receipt before provider I/O or irreversible transitions.
        DB::table('snippe_webhook_receipts')->insertOrIgnore(['id' => (string) Str::ulid(), 'event_key' => $key, 'body_hash' => $hash, 'type' => $event['type'], 'provider_reference' => $reference, 'outcome' => 'received', 'created_at' => now('UTC')]);
        $receipt = DB::table('snippe_webhook_receipts')->where('event_key', $key)->first();
        if ($receipt->body_hash !== $hash) {
            return 'event_conflict';
        }
        if ($receipt->processed_at !== null) {
            return 'processed';
        }
        $payment = Payment::query()->where('provider', 'snippe')->where('method', 'mobile_money')->where('provider_payment_reference', $reference)->first();
        $outcome = 'unknown_reference';
        if (! in_array($event['type'], ['payment.completed', 'payment.failed', 'payment.expired', 'payment.voided'], true)) {
            $outcome = 'ignored';
        } elseif ($payment) {
            $order = Order::query()->findOrFail($payment->order_id);
            $metadata = $data['metadata'] ?? [];
            $mismatch = ! is_array($metadata) || ($data['amount']['value'] ?? null) !== $payment->provider_amount_tzs || ($data['amount']['currency'] ?? null) !== $payment->currency || ($data['status'] ?? null) !== substr($event['type'], 8);
            foreach (['order_id' => $order->id, 'order_number' => $order->order_number, 'payment_attempt' => $payment->attempt_key] as $field => $value) {
                $mismatch = $mismatch || (is_array($metadata) && isset($metadata[$field]) && $metadata[$field] !== $value);
            }
            if ($mismatch) {
                DB::transaction(function () use ($payment) {
                    Order::query()->lockForUpdate()->findOrFail($payment->order_id);
                    $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                    $payment->update(['status' => $payment->status === PaymentStatus::Completed ? $payment->status : PaymentStatus::AttentionRequired, 'reconciliation_issue' => 'webhook_mismatch']);
                });
                $outcome = 'webhook_mismatch';
            } elseif ($payment->active_order_id === null) {
                // Repeated final evidence is inert; contradictory late success needs review.
                $outcome = $payment->status->value === $data['status'] || $payment->status === PaymentStatus::Completed ? 'processed' : 'incompatible_lifecycle';
                if ($outcome !== 'processed') {
                    $payment->update(['status' => PaymentStatus::AttentionRequired, 'reconciliation_issue' => $outcome]);
                }
            } else {
                $payment = app(MobileMoneyPayment::class)->refresh($payment, webhook: true);
                $outcome = $payment->reconciliation_issue ?? ($payment->active_order_id === null ? 'processed' : 'verification_pending');
            }
        }
        DB::table('snippe_webhook_receipts')->where('event_key', $key)->whereNull('processed_at')->update(['outcome' => $outcome, 'processed_at' => in_array($outcome, ['processed', 'ignored'], true) ? now('UTC') : null]);

        return $outcome;
    }
}
