<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Identity\Support\PermissionMetadata;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleMetadata;
use App\Domain\Identity\Support\RoleRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Spatie\Permission\Models\Role;

final class RoleAccessController extends Controller
{
    public function index(): View
    {
        $counts = Role::query()
            ->where('guard_name', PermissionRegistry::GUARD)
            ->whereIn('name', array_keys(RoleRegistry::permissionBundles()))
            ->withCount('users')
            ->pluck('users_count', 'name');

        return view('admin.roles.index', [
            'roles' => RoleMetadata::all(),
            'bundles' => RoleRegistry::permissionBundles(),
            'counts' => $counts,
        ]);
    }

    public function show(string $role): View
    {
        abort_unless(RoleRegistry::contains($role), 404);

        $databaseRole = Role::query()
            ->where('guard_name', PermissionRegistry::GUARD)
            ->where('name', $role)
            ->with('permissions')
            ->withCount('users')
            ->first();
        $registeredPermissions = collect(RoleRegistry::permissionBundles()[$role])->sort()->values();
        $databasePermissions = $databaseRole?->permissions->pluck('name')->sort()->values() ?? collect();

        return view('admin.roles.show', [
            'role' => $role,
            'metadata' => RoleMetadata::for($role),
            'permissionMetadata' => PermissionMetadata::all(),
            'permissions' => $registeredPermissions,
            'userCount' => $databaseRole === null ? 0 : (int) $databaseRole->users_count,
            'drift' => $databaseRole === null || $databasePermissions->all() !== $registeredPermissions->all(),
        ]);
    }
}
