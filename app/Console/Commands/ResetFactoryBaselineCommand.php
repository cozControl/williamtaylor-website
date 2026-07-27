<?php

namespace App\Console\Commands;

use App\Domain\Factory\Services\FactoryAboutPageInstaller;
use App\Domain\Factory\Services\FactoryContentInstaller;
use App\Domain\Factory\Services\FactoryEnvironmentGuard;
use App\Domain\Factory\Services\FactoryManifest;
use App\Models\User;
use Database\Seeders\FactoryIdentitySeeder;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class ResetFactoryBaselineCommand extends Command
{
    protected $signature = 'factory:reset {--factory=william-taylor-factory-v1} {--scope=content} {--apply} {--include-password-reset} {--confirm=} {--backup-acknowledged}';

    protected $description = 'Preview or restore a scoped factory baseline without deleting provider binaries or non-factory data.';

    public function handle(FactoryManifest $manifest, FactoryEnvironmentGuard $guard, FactoryContentInstaller $content, FactoryAboutPageInstaller $about): int
    {
        $version = (string) $this->option('factory');
        if (! in_array($version, [FactoryManifest::VERSION, FactoryManifest::VERSION_2], true)) {
            throw new InvalidArgumentException('Unknown factory version.');
        }
        config(['factory.runtime_version' => $version]);
        $scope = (string) $this->option('scope');
        if (! in_array($scope, ['content', 'identities', 'all'], true)) {
            throw new InvalidArgumentException('Factory reset scope must be content, identities, or all.');
        }
        $this->components->info('Factory: '.$version);
        $this->line('Reset scope: '.$scope.'. Manifest checksum: '.$manifest->checksum());
        $this->line('Non-factory records and Cloudinary binaries will be preserved.');
        if (! $this->option('apply')) {
            $this->comment('Preview only. No database or provider mutation occurred.');

            return self::SUCCESS;
        }
        $guard->assertResetAllowed();
        if ($scope === 'all' && ($this->option('confirm') !== config('factory.full_reset_phrase') || ! $this->option('backup-acknowledged'))) {
            throw new InvalidArgumentException('Full reset requires the exact confirmation phrase and backup acknowledgment.');
        }
        if ($this->option('include-password-reset') && ! in_array($scope, ['identities', 'all'], true)) {
            throw new InvalidArgumentException('Password reset is available only for identities or all scope.');
        }
        if (in_array($scope, ['identities', 'all'], true)) {
            app(FactoryIdentitySeeder::class)->install((bool) $this->option('include-password-reset'));
        }
        if (in_array($scope, ['content', 'all'], true)) {
            $email = mb_strtolower(trim((string) config('factory.users.administrator.email')));
            $actor = User::query()->whereRaw('LOWER(email) = ?', [$email])->firstOrFail();
            $content->apply($actor, true);
            if ($version === FactoryManifest::VERSION_2) {
                $about->apply($actor, true);
            }
        }
        $this->components->info('Factory reset scope completed. Cloudinary binaries were not deleted.');

        return self::SUCCESS;
    }
}
