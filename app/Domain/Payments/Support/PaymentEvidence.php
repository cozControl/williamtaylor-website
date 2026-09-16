<?php

namespace App\Domain\Payments\Support;

final readonly class PaymentEvidence
{
    /** @param array<string, mixed> $metadata */
    public function __construct(public string $reference, public string $status, public int $amount, public string $currency, public array $metadata, public ?string $expiresAt) {}
}
