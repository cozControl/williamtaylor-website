<?php

namespace App\Console\Commands;

use App\Domain\Factory\Services\FactoryEnvironmentGuard;
use App\Domain\Factory\Services\FactoryManifest;
use App\Domain\Factory\Services\FactoryMediaSynchronizer;
use App\Models\User;
use Illuminate\Console\Command;
use RuntimeException;

final class SynchronizeFactoryMediaCommand extends Command
{
    protected $signature = 'factory:media-sync {--apply} {--factory=william-taylor-factory-v1} {--only=} {--retry-failed} {--include-remote}';

    protected $description = 'Preview or synchronize the versioned factory Media manifest.';

    public function handle(FactoryManifest $manifest, FactoryMediaSynchronizer $sync, FactoryEnvironmentGuard $guard): int
    {
        $version = (string) $this->option('factory');
        if (! in_array($version, [FactoryManifest::VERSION, FactoryManifest::VERSION_2], true)) {
            $this->error('Unknown factory version.');

            return self::FAILURE;
        }
        config(['factory.runtime_version' => $version]);
        $plan = $sync->plan($this->option('only'), (bool) $this->option('include-remote'));
        $this->components->info('Factory: '.$version);
        $this->line('Media manifest checksum: '.$manifest->mediaChecksum());
        $this->table(['Upload', 'Reuse', 'Deferred', 'Drift'], [array_values($plan)]);
        if (! $this->option('apply')) {
            $this->comment('Preview only. No provider or database mutation occurred.');

            return self::SUCCESS;
        }
        $guard->assertSafeDatabase();
        $email = mb_strtolower(trim((string) config('factory.users.administrator.email')));
        $actor = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($actor === null) {
            throw new RuntimeException('The configured factory Administrator must be installed before Media synchronization.');
        }
        $sync->apply($actor, $this->option('only'), (bool) $this->option('include-remote'));
        $this->components->info('Factory Media synchronization completed without exposing provider credentials.');

        return self::SUCCESS;
    }
}
