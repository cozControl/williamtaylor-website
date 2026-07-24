<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Identity\Exceptions\RoleMutationReasonRequiredException;
use App\Domain\Identity\Exceptions\UnregisteredRoleException;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

final class AssignRoleToUser
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(User $actor, User $target, string $role, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new RoleMutationReasonRequiredException;
        }

        if (! RoleRegistry::contains($role)) {
            throw new UnregisteredRoleException($role);
        }

        Gate::forUser($actor)->authorize('assignRole', $target);

        DB::transaction(function () use ($actor, $target, $role, $reason): void {
            $lockedTarget = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $before = $lockedTarget->getRoleNames()->sort()->values()->all();

            if ($lockedTarget->hasRole($role)) {
                return;
            }

            app(ControlledRoleMutation::class)->run(
                fn () => $lockedTarget->assignRole($role),
            );
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->audit->handle(
                action: 'identity.role.assigned',
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
