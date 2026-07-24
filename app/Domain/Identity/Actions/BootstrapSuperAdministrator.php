<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class BootstrapSuperAdministrator
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(User $target, string $reason): void
    {
        if ($target->email_verified_at === null) {
            throw new RuntimeException('The bootstrap user must have a verified email address.');
        }

        DB::transaction(function () use ($target, $reason): void {
            Role::query()->where('name', RoleRegistry::SUPER_ADMINISTRATOR)
                ->where('guard_name', PermissionRegistry::GUARD)->lockForUpdate()->firstOrFail();

            if (User::query()->role(RoleRegistry::SUPER_ADMINISTRATOR)->lockForUpdate()->exists()) {
                throw new RuntimeException('A Super Administrator already exists; use an authorized assignment action.');
            }

            $lockedTarget = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $before = $lockedTarget->getRoleNames()->sort()->values()->all();
            app(ControlledRoleMutation::class)->run(
                fn () => $lockedTarget->assignRole(RoleRegistry::SUPER_ADMINISTRATOR),
            );
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->audit->handle(
                action: 'identity.super-administrator.bootstrapped', resource: $lockedTarget, actor: null,
                before: ['roles' => $before],
                after: ['roles' => $lockedTarget->fresh()->getRoleNames()->sort()->values()->all()],
                reason: $reason,
            );
        }, 3);
    }
}
