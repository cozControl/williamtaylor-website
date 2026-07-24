<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use LogicException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class ProvisionRegisteredAccess
{
    public function handle(): void
    {
        $unregistered = Permission::query()
            ->where('guard_name', PermissionRegistry::GUARD)
            ->whereNotIn('name', PermissionRegistry::all())
            ->pluck('name')->sort()->values();

        if ($unregistered->isNotEmpty()) {
            throw new LogicException(
                'Unregistered permissions require rbac:align-foundation-registry preview and explicit apply: '
                .$unregistered->implode(', '),
            );
        }

        foreach (PermissionRegistry::all() as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => PermissionRegistry::GUARD,
            ]);
        }

        foreach (RoleRegistry::permissionBundles() as $roleName => $permissions) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => PermissionRegistry::GUARD,
            ]);
            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
