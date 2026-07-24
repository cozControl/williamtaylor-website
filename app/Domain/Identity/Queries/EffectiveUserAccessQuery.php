<?php

namespace App\Domain\Identity\Queries;

use App\Domain\Identity\Data\EffectiveAccessPreview;
use App\Domain\Identity\Data\EffectiveUserAccess;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\PreviewFingerprint;
use App\Domain\Identity\Support\RoleRegistry;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class EffectiveUserAccessQuery
{
    public function for(User $user): EffectiveUserAccess
    {
        $assignedRoles = array_values($user->getRoleNames()
            ->map(function (mixed $role): string {
                if (! is_string($role)) {
                    throw new LogicException('Role names must be strings.');
                }

                return $role;
            })
            ->sort()
            ->values()
            ->all());
        $directPivot = config('permission.table_names.model_has_permissions');
        if (! is_string($directPivot)) {
            throw new LogicException('Permission pivot table configuration is invalid.');
        }
        $roles = Role::query()
            ->where('guard_name', PermissionRegistry::GUARD)
            ->whereIn('name', $assignedRoles)
            ->with('permissions')
            ->get();
        $directPermissionIds = DB::table($directPivot)
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', $user->getKey())
            ->pluck('permission_id');
        $directPermissions = Permission::query()
            ->whereIn('id', $directPermissionIds)
            ->get();
        $super = in_array(RoleRegistry::SUPER_ADMINISTRATOR, $assignedRoles, true);
        $sources = [];
        $warnings = [];

        foreach ($roles as $role) {
            if (! RoleRegistry::contains($role->name)) {
                $warnings[] = "Unknown role assigned: {$role->name}.";
            }

            foreach ($role->permissions as $permission) {
                if (PermissionRegistry::contains($permission->name)) {
                    $sources[$permission->name][] = "Role: {$role->name}";
                } else {
                    $warnings[] = "Unknown permission granted by {$role->name}: {$permission->name}.";
                }
            }

            if (RoleRegistry::contains($role->name)) {
                $actual = $role->permissions->pluck('name')->sort()->values()->all();
                $registered = collect(RoleRegistry::permissionBundles()[$role->name])->sort()->values()->all();

                if ($actual !== $registered) {
                    $warnings[] = "Registered role drift detected for {$role->name}. Run php artisan rbac:audit.";
                }
            }
        }

        foreach ($directPermissions as $permission) {
            if (PermissionRegistry::contains($permission->name)) {
                $sources[$permission->name][] = 'Direct grant (registry warning)';
                $warnings[] = "Direct registered permission grant detected: {$permission->name}.";
            } else {
                $warnings[] = "Unknown direct permission detected: {$permission->name}.";
            }
        }

        if ($super) {
            foreach (PermissionRegistry::all() as $permission) {
                $sources[$permission][] = 'Super Administrator bypass';
            }
        }

        foreach ($sources as &$permissionSources) {
            $permissionSources = array_values(array_unique($permissionSources));
            sort($permissionSources);
        }
        unset($permissionSources);
        ksort($sources);

        $effective = array_values(array_intersect(PermissionRegistry::all(), array_keys($sources)));
        $duplicates = array_keys(array_filter($sources, fn (array $items): bool => count($items) > 1));

        return new EffectiveUserAccess(
            userId: (int) $user->getKey(),
            name: $user->name,
            email: $user->email,
            assignedRoles: $assignedRoles,
            effectivePermissions: $effective,
            permissionSources: $sources,
            duplicateGrants: $duplicates,
            hasAdministrativeAccess: in_array(PermissionRegistry::ADMIN_ACCESS, $effective, true),
            usesSuperAdministratorBypass: $super,
            warnings: array_values(array_unique($warnings)),
        );
    }

    /** @param list<string> $proposedRoles */
    public function preview(User $user, array $proposedRoles): EffectiveAccessPreview
    {
        $current = $this->for($user);
        $proposedRoles = array_values(array_unique(array_filter(
            $proposedRoles,
            fn (string $role): bool => RoleRegistry::contains($role),
        )));
        sort($proposedRoles);

        $sources = [];
        $warnings = $current->warnings;
        $roles = Role::query()
            ->where('guard_name', PermissionRegistry::GUARD)
            ->whereIn('name', $proposedRoles)
            ->with('permissions')
            ->get()
            ->keyBy('name');

        foreach ($proposedRoles as $roleName) {
            $role = $roles->get($roleName);

            if (! $role instanceof Role) {
                $warnings[] = "Registered role is missing from persistence: {$roleName}.";

                continue;
            }

            foreach ($role->permissions as $permission) {
                if (PermissionRegistry::contains($permission->name)) {
                    $sources[$permission->name][] = "Role: {$roleName}";
                } else {
                    $warnings[] = "Unknown permission granted by {$roleName}: {$permission->name}.";
                }
            }
        }

        $directPermissions = $this->directPermissions($user);
        $directRegisteredNames = [];
        foreach ($directPermissions as $permission) {
            if (PermissionRegistry::contains($permission->name)) {
                $directRegisteredNames[] = $permission->name;
                $sources[$permission->name][] = 'Direct grant (registry warning)';
            } else {
                $warnings[] = "Unknown direct permission detected: {$permission->name}.";
            }
        }
        sort($directRegisteredNames);

        $proposedSuper = in_array(RoleRegistry::SUPER_ADMINISTRATOR, $proposedRoles, true);
        if ($proposedSuper) {
            foreach (PermissionRegistry::all() as $permission) {
                $sources[$permission][] = 'Super Administrator bypass';
            }
        }

        foreach ($sources as &$permissionSources) {
            $permissionSources = array_values(array_unique($permissionSources));
            sort($permissionSources);
        }
        unset($permissionSources);
        ksort($sources);

        $proposedPermissions = array_values(array_intersect(PermissionRegistry::all(), array_keys($sources)));
        sort($proposedPermissions);
        $duplicates = array_keys(array_filter($sources, fn (array $items): bool => count($items) > 1));
        sort($duplicates);
        $currentPermissions = $current->effectivePermissions;
        sort($currentPermissions);
        $gained = array_values(array_diff($proposedPermissions, $currentPermissions));
        $lost = array_values(array_diff($currentPermissions, $proposedPermissions));
        sort($gained);
        sort($lost);
        $currentAdmin = $current->hasAdministrativeAccess;
        $proposedAdmin = in_array(PermissionRegistry::ADMIN_ACCESS, $proposedPermissions, true);
        $currentSuper = in_array(RoleRegistry::SUPER_ADMINISTRATOR, $current->assignedRoles, true);
        $warnings = array_values(array_unique($warnings));
        sort($warnings);
        $registryChecksum = $this->checksum(PermissionRegistry::all());
        $roleBundleChecksum = $this->checksum([
            'registered' => RoleRegistry::permissionBundles(),
            'persisted' => $roles->map(
                fn (Role $role): array => $role->permissions->pluck('name')->sort()->values()->all(),
            )->sortKeys()->all(),
        ]);
        $fingerprintState = [
            'targetUserId' => (int) $user->getKey(),
            'currentRoles' => $current->assignedRoles,
            'proposedRoles' => $proposedRoles,
            'directRegisteredPermissions' => $directRegisteredNames,
            'currentEffectivePermissions' => $currentPermissions,
            'proposedEffectivePermissions' => $proposedPermissions,
            'currentPermissionSources' => $current->permissionSources,
            'proposedPermissionSources' => $sources,
            'gainedPermissions' => $gained,
            'lostPermissions' => $lost,
            'willHaveAdministrativeAccess' => $proposedAdmin,
            'willBeSuperAdministrator' => $proposedSuper,
        ];

        return new EffectiveAccessPreview(
            currentRoles: $current->assignedRoles,
            proposedRoles: $proposedRoles,
            currentEffectivePermissions: $currentPermissions,
            proposedEffectivePermissions: $proposedPermissions,
            gainedPermissions: $gained,
            lostPermissions: $lost,
            currentPermissionSources: $current->permissionSources,
            proposedPermissionSources: $sources,
            duplicateGrants: $duplicates,
            administrativeAccessChanges: $currentAdmin !== $proposedAdmin,
            willHaveAdministrativeAccess: $proposedAdmin,
            superAdministratorChanges: $currentSuper !== $proposedSuper,
            willBeSuperAdministrator: $proposedSuper,
            warnings: $warnings,
            fingerprint: app(PreviewFingerprint::class)->hash(
                $fingerprintState,
                $registryChecksum,
                $roleBundleChecksum,
            ),
        );
    }

    /** @return Collection<int, Permission> */
    private function directPermissions(User $user): Collection
    {
        $directPivot = config('permission.table_names.model_has_permissions');
        if (! is_string($directPivot)) {
            throw new LogicException('Permission pivot table configuration is invalid.');
        }

        $permissionIds = DB::table($directPivot)
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', $user->getKey())
            ->pluck('permission_id');

        return Permission::query()->whereIn('id', $permissionIds)->get();
    }

    /** @param array<mixed> $value */
    private function checksum(array $value): string
    {
        return app(PreviewFingerprint::class)->hash(
            ['value' => $value],
            'checksum',
            'checksum',
        );
    }
}
