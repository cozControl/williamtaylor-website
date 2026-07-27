<?php

namespace App\Console\Commands;

use App\Domain\Factory\Services\FactoryAboutPageInstaller;
use App\Domain\Factory\Services\FactoryContentInstaller;
use App\Domain\Factory\Services\FactoryEnvironmentGuard;
use App\Domain\Factory\Services\FactoryManifest;
use App\Domain\Identity\Actions\AlignFoundationRegistry;
use App\Models\User;
use Database\Seeders\FactoryIdentitySeeder;
use Illuminate\Console\Command;

final class InstallFactoryBaselineCommand extends Command
{
    protected $signature = 'factory:install {--apply} {--factory=william-taylor-factory-v1} {--include-password-reset}';

    protected $description = 'Preview or install the deterministic William Taylor factory baseline.';

    public function handle(FactoryManifest $manifest, FactoryEnvironmentGuard $guard, AlignFoundationRegistry $alignment, FactoryContentInstaller $content, FactoryAboutPageInstaller $about): int
    {
        $version = (string) $this->option('factory');
        if (! in_array($version, [FactoryManifest::VERSION, FactoryManifest::VERSION_2], true)) {
            $this->error('Unknown factory version.');

            return self::FAILURE;
        }
        config(['factory.runtime_version' => $version]);
        $this->components->info('Factory: '.$version);
        $this->line('Manifest checksum: '.$manifest->checksum());
        $this->line('Media manifest checksum: '.$manifest->mediaChecksum());
        $this->line('Identities: 3; roles: 3; Site Content resources: '.count($manifest->content()).'; Media entries: '.count($manifest->media()).'.');
        $rolePlan = $alignment->inspect();
        $this->line('Role bundle changes: '.count($rolePlan->roleBundleChanges).'.');
        if (! $this->option('apply')) {
            $this->comment('Preview only. Password values are intentionally omitted.');

            return self::SUCCESS;
        }
        $guard->assertSafeDatabase();
        $alignment->apply('Explicit '.$version.' installation');
        $identityResult = app(FactoryIdentitySeeder::class)->install((bool) $this->option('include-password-reset'));
        $email = mb_strtolower(trim((string) config('factory.users.administrator.email')));
        $actor = User::query()->whereRaw('LOWER(email) = ?', [$email])->firstOrFail();
        $contentResult = $content->apply($actor);
        $aboutResult = $version === FactoryManifest::VERSION_2 ? $about->apply($actor) : ['created' => 0, 'reused' => 0, 'restored' => 0];
        $this->table(['Users created', 'Users reused', 'Roles assigned', 'Passwords reset'], [array_values($identityResult)]);
        $this->table(['Content created', 'Content reused', 'Content restored', 'Revisions', 'Transitions'], [array_values($contentResult)]);
        $this->table(['About created', 'About reused', 'About restored'], [array_values($aboutResult)]);
        $this->components->info('Factory baseline installation completed.');

        return self::SUCCESS;
    }
}
