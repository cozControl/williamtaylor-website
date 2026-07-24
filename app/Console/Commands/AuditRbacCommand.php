<?php

namespace App\Console\Commands;

use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class AuditRbacCommand extends Command
{
    protected $signature = 'rbac:audit';

    protected $description = 'Verify persisted roles, permissions, bundles, and assignments against the application registry';

    public function handle(): int
    {
        $failures = [];
        $registeredPermissions = collect(PermissionRegistry::all())->sort()->values();
        $persistedPermissions = Permission::query()
            ->where('guard_name', PermissionRegistry::GUARD)
            ->pluck('name')->sort()->values();

        if ($registeredPermissions->all() !== $persistedPermissions->all()) {
            $failures[] = 'Persisted permissions differ from PermissionRegistry.';
        }

        $registeredRoles = collect(array_keys(RoleRegistry::permissionBundles()))->sort()->values();
        $persistedRoles = Role::query()
            ->where('guard_name', PermissionRegistry::GUARD)
            ->pluck('name')->sort()->values();

        if ($registeredRoles->all() !== $persistedRoles->all()) {
            $failures[] = 'Persisted roles differ from RoleRegistry.';
        }

        foreach (RoleRegistry::permissionBundles() as $roleName => $expectedPermissions) {
            $role = Role::query()->where('name', $roleName)
                ->where('guard_name', PermissionRegistry::GUARD)->first();

            if ($role === null) {
                continue;
            }

            $actual = $role->permissions->pluck('name')->sort()->values()->all();
            $expected = collect($expectedPermissions)->sort()->values()->all();

            if ($actual !== $expected) {
                $failures[] = "Permission bundle drift detected for [{$roleName}].";
            }
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->error($failure);
            }

            return self::FAILURE;
        }

        $this->info('RBAC registry, persisted bundles, and guard are consistent.');

        return self::SUCCESS;
    }
}
