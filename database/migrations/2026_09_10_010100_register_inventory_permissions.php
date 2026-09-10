<?php

use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([PermissionRegistry::INVENTORY_VIEW, PermissionRegistry::INVENTORY_MANAGE] as $name) {
            Permission::findOrCreate($name, PermissionRegistry::GUARD);
        }
        foreach ([RoleRegistry::SUPER_ADMINISTRATOR, RoleRegistry::INVENTORY_MANAGER] as $name) {
            $role = Role::query()->where('name', $name)->where('guard_name', PermissionRegistry::GUARD)->first();
            if ($role !== null) {
                $role->givePermissionTo([PermissionRegistry::INVENTORY_VIEW, PermissionRegistry::INVENTORY_MANAGE]);
                if ($name === RoleRegistry::INVENTORY_MANAGER) {
                    $role->givePermissionTo(Permission::findOrCreate(PermissionRegistry::PRODUCTS_VIEW, PermissionRegistry::GUARD));
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Permissions may already be assigned operationally; do not revoke access on rollback.
    }
};
