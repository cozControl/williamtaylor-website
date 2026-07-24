<?php

namespace App\Domain\Media\Data;

final readonly class UploadIntent
{
    /** @param array<string, scalar> $parameters */
    public function __construct(public string $endpoint, public array $parameters, public int $expiresAt) {}
}
