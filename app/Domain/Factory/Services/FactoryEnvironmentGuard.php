<?php

namespace App\Domain\Factory\Services;

use RuntimeException;

final class FactoryEnvironmentGuard
{
    public function assertSafeDatabase(): void
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');
        if ($database !== '' && strtolower(basename(str_replace('\\', '/', $database))) === 'willy') {
            throw new RuntimeException('Factory apply is prohibited against the tracked willy database.');
        }
    }

    public function assertResetAllowed(): void
    {
        $this->assertSafeDatabase();
        if (! app()->environment(['local', 'testing', 'staging'])) {
            throw new RuntimeException('Factory reset apply is prohibited in this environment.');
        }
    }
}
