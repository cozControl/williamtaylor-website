<?php

namespace App\Console\Commands;

use App\Domain\Identity\Actions\BootstrapSuperAdministrator;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

final class BootstrapSuperAdministratorCommand extends Command
{
    protected $signature = 'rbac:bootstrap-super-admin
        {email : Existing verified user email address}
        {--reason=Initial controlled Super Administrator bootstrap : Audit reason}
        {--force : Skip the interactive confirmation}';

    protected $description = 'Assign the first Super Administrator role to an existing verified user';

    public function handle(ProvisionRegisteredAccess $provision, BootstrapSuperAdministrator $bootstrap): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            $this->error('No existing user matches that email address. No user was created.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Assign the first Super Administrator role to {$user->email}?")) {
            $this->warn('Bootstrap cancelled; no role was assigned.');

            return self::FAILURE;
        }

        try {
            $provision->handle();
            $bootstrap->handle($user, trim((string) $this->option('reason')));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('The first Super Administrator role was assigned and audited.');

        return self::SUCCESS;
    }
}
