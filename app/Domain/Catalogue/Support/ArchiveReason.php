<?php

namespace App\Domain\Catalogue\Support;

use InvalidArgumentException;

final class ArchiveReason
{
    public static function normalize(string $reason): string
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('An archive reason is required.');
        }

        if (mb_strlen($reason) > 1000) {
            throw new InvalidArgumentException('An archive reason may not exceed 1000 characters.');
        }

        return $reason;
    }
}
