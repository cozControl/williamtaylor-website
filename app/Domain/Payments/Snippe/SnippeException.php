<?php

namespace App\Domain\Payments\Snippe;

final class SnippeException extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly bool $ambiguous = true, public readonly int $retryAfter = 60)
    {
        parent::__construct('Secure payment is temporarily unavailable.');
    }
}
