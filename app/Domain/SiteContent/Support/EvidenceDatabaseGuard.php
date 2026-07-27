<?php

namespace App\Domain\SiteContent\Support;

use RuntimeException;

final class EvidenceDatabaseGuard
{
    public static function assertDisposable(string $database): void
    {
        $tracked = realpath(base_path('willy')) ?: base_path('willy');
        $candidate = realpath($database) ?: $database;

        if ($database === '' || $database === ':memory:' || strcasecmp($tracked, $candidate) === 0) {
            throw new RuntimeException('Browser evidence requires a disposable SQLite file distinct from tracked willy.');
        }
    }
}
