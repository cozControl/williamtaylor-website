<?php

namespace App\Domain\Payments\Snippe;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Support\PaymentEvidence;

final class SnippePaymentGateway implements PaymentGateway
{
    public function __construct(private SnippeClient $client) {}

    public function initiate(array $request, string $idempotencyKey): PaymentEvidence
    {
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 30) {
            throw new SnippeException('invalid_attempt_key', false);
        }

        return $this->normalize($this->client->request('POST', '/v1/payments', $request, $idempotencyKey));
    }

    public function status(string $reference): PaymentEvidence
    {
        return $this->normalize($this->client->request('GET', '/v1/payments/'.rawurlencode($reference), retryGet: false));
    }

    /** @param array<array-key, mixed> $data */
    private function normalize(array $data): PaymentEvidence
    {
        if (! is_string($data['reference'] ?? null) || ! preg_match('/^[A-Za-z0-9_-]{1,191}$/D', $data['reference']) || ! in_array($data['status'] ?? null, ['pending', 'completed', 'failed', 'expired', 'voided'], true) || ! is_int($data['amount']['value'] ?? null) || (! is_string($data['amount']['currency'] ?? null) || ! preg_match('/^[A-Z]{3}$/D', $data['amount']['currency'])) || (isset($data['metadata']) && ! is_array($data['metadata']))) {
            throw new SnippeException('malformed_response');
        }
        $expires = $data['expires_at'] ?? null;
        if ($expires !== null && (! is_string($expires) || strlen($expires) > 64 || ! preg_match('/^\d{4}-\d{2}-\d{2}T/', $expires) || strtotime($expires) === false)) {
            throw new SnippeException('malformed_response');
        }

        return new PaymentEvidence($data['reference'], $data['status'], $data['amount']['value'], $data['amount']['currency'], $data['metadata'] ?? [], $expires);
    }
}
