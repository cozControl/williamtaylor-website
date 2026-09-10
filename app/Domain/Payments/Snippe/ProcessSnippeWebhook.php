<?php

namespace App\Domain\Payments\Snippe;

use App\Domain\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProcessSnippeWebhook
{
    /** @param array<string, mixed> $event */
    public function process(array $event, string $body): string
    {
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
}
