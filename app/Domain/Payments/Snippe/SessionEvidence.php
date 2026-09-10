<?php

namespace App\Domain\Payments\Snippe;

final readonly class SessionEvidence
{
    /** @param array<string, mixed> $metadata */
    public function __construct(public string $reference, public string $status, public int $amount, public string $currency, public ?string $checkoutUrl, public array $metadata, public ?string $expiresAt) {}

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (! is_string($data['reference'] ?? null) || ! preg_match('/^[A-Za-z0-9_-]{1,191}$/D', $data['reference']) || ! in_array($data['status'] ?? null, ['pending', 'active', 'completed', 'expired', 'cancelled'], true) || ! is_int($data['amount'] ?? null) || $data['amount'] < 500 || ! is_string($data['currency'] ?? null) || (isset($data['metadata']) && ! is_array($data['metadata']))) {
            throw new SnippeException('malformed_response');
        }
        $url = $data['checkout_url'] ?? null;
        if ($url !== null && (! is_string($url) || strlen($url) > 2000 || parse_url($url, PHP_URL_SCHEME) !== 'https' || ! in_array(parse_url($url, PHP_URL_HOST), config('snippe.checkout_hosts'), true) || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PORT) !== null)) {
            throw new SnippeException('unsafe_checkout_url');
        }
        $expires = $data['expires_at'] ?? null;
        if ($expires !== null && (! is_string($expires) || ! preg_match('/^\d{4}-\d{2}-\d{2}T/', $expires) || strtotime($expires) === false)) {
            throw new SnippeException('malformed_response');
        }

        return new self($data['reference'], $data['status'], $data['amount'], $data['currency'], $url, $data['metadata'] ?? [], $expires);
    }
}
