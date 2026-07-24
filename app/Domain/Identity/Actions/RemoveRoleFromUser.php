<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Identity\Exceptions\FinalSuperAdministratorException;
use App\Domain\Identity\Exceptions\RoleMutationReasonRequiredException;
use App\Domain\Identity\Exceptions\SelfLockoutException;
use App\Domain\Identity\Exceptions\UnregisteredRoleException;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RemoveRoleFromUser
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(User $actor, User $target, string $role, string $reason): void
    {
        if (! RoleRegistry::contains($role)) {
            $reason = trim($reason);

            if ($reason === '') {
                throw new RoleMutationReasonRequiredException;
            }

            throw new UnregisteredRoleException($role);
        }

        Gate::forUser($actor)->authorize('revokeRole', $target);

        DB::transaction(function () use ($actor, $target, $role, $reason): void {
            Role::query()
                ->where('name', $role)
                ->where('guard_name', PermissionRegistry::GUARD)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedTarget = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $before = $lockedTarget->getRoleNames()->sort()->values()->all();

            if (! $lockedTarget->hasRole($role)) {
                return;
            }

            if ($role === RoleRegistry::SUPER_ADMINISTRATOR
                && User::query()->role(RoleRegistry::SUPER_ADMINISTRATOR)->lockForUpdate()->count() <= 1) {
                throw new FinalSuperAdministratorException;
            }

            app(ControlledRoleMutation::class)->run(
                fn () => $lockedTarget->removeRole($role),
            );
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            if ($actor->is($lockedTarget)) {
                $remainingRoles = $lockedTarget->getRoleNames()
                    ->reject(fn (string $assignedRole): bool => $assignedRole === $role);
                $retainsAdministration = $remainingRoles->contains(
                    fn (string $assignedRole): bool => in_array(
                        PermissionRegistry::ADMIN_ACCESS,
                        RoleRegistry::permissionBundles()[$assignedRole] ?? [],
                        true,
                    ),
                ) || $lockedTarget->permissions()
                    ->where('name', PermissionRegistry::ADMIN_ACCESS)
                    ->exists();

                if (! $retainsAdministration) {
                    throw new SelfLockoutException;
                }
            }

            $this->audit->handle(
                action: 'identity.role.revoked',
                resource: $lockedTarget,
                actor: $actor,
                before: ['roles' => $before],
                after: ['roles' => $lockedTarget->fresh()->getRoleNames()->sort()->values()->all()],
                permission: PermissionRegistry::ROLES_MANAGE,
                reason: $reason,
            );
        }, 3);
    }
}
