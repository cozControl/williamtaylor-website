<?php

namespace App\Domain\Payments\Snippe;

use Illuminate\Http\Request;

final class VerifySnippeWebhook
{
    /** @return array<string, mixed> */
    public function verify(Request $request): array
    {
        $raw = $request->getContent();
        $timestamp = $request->header('X-Webhook-Timestamp', '');
        $signature = $request->header('X-Webhook-Signature', '');
        $secret = config('snippe.webhook_secret');
        abort_unless(is_string($secret) && $secret !== '' && preg_match('/^[0-9]{10}$/D', $timestamp) && abs(time() - (int) $timestamp) <= 300 && preg_match('/^[a-f0-9]{64}$/D', $signature) && strlen($raw) <= 131072 && hash_equals(hash_hmac('sha256', $timestamp.'.'.$raw, $secret), $signature), 401);
        try {
            $event = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            abort(400);
        }
        abort_unless(is_array($event) && is_string($event['type'] ?? null) && strlen($event['type']) <= 64 && is_array($event['data'] ?? null) && (! isset($event['api_version']) || $event['api_version'] === '2026-01-25') && (! isset($event['id']) || (is_string($event['id']) && strlen($event['id']) <= 191)), 400);

        return $event;
    }
}
