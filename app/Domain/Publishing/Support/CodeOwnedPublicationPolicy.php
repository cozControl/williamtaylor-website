<?php

namespace App\Domain\Publishing\Support;

use App\Domain\Publishing\Contracts\PublicationPolicy;
use InvalidArgumentException;

final class CodeOwnedPublicationPolicy implements PublicationPolicy
{
    /** @var array<string, bool> */
    private const SELF_APPROVAL = [
        'standard' => true,
        'landing' => true,
    ];

    public function allowsSelfApproval(string $pageType): bool
    {
        return self::SELF_APPROVAL[$pageType] ?? throw new InvalidArgumentException('Unknown publication policy.');
    }

    public function checksum(): string
    {
        return hash('sha256', json_encode(self::SELF_APPROVAL, JSON_THROW_ON_ERROR));
    }
}
