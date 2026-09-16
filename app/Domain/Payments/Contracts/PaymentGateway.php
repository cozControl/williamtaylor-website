<?php

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\Support\PaymentEvidence;

interface PaymentGateway
{
    /** @param array<string, mixed> $request */
    public function initiate(array $request, string $idempotencyKey): PaymentEvidence;

    public function status(string $reference): PaymentEvidence;
}
