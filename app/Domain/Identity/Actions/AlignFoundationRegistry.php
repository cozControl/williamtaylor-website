<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Identity\Exceptions\UnexpectedFoundationAssignmentException;
use App\Domain\Identity\Support\FoundationRegistryAlignmentPlan;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class AlignFoundationRegistry
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function inspect(): FoundationRegistryAlignmentPlan
    {
        $persisted = Permission::query()->where('guard_name', PermissionRegistry::GUARD)
            ->pluck('name')->sort()->values()->all();
        $approved = PermissionRegistry::all();
        $additions = array_values(array_diff($approved, $persisted));
        $removals = array_values(array_diff($persisted, $approved));
        sort($additions);
        sort($removals);

        $roleBundleChanges = [];
        foreach (RoleRegistry::permissionBundles() as $roleName => $after) {
            $role = Role::query()->where('name', $roleName)
                ->where('guard_name', PermissionRegistry::GUARD)->first();
            $before = $role?->permissions->pluck('name')->map(
                fn (mixed $name): string => (string) $name,
            )->sort()->values()->all() ?? [];
            $after = array_values(collect($after)->sort()->values()->all());

            if ($before !== $after) {
                $roleBundleChanges[$roleName] = ['before' => $before, 'after' => $after];
            }
        }
        ksort($roleBundleChanges);

        $directAssignments = [];
        $unexpectedRoleAssignments = [];
        if ($removals !== []) {
            $directAssignments = DB::table(config('permission.table_names.model_has_permissions').' as assignment')
                ->join(config('permission.table_names.permissions').' as permission', 'permission.id', '=', 'assignment.permission_id')
                ->where('permission.guard_name', PermissionRegistry::GUARD)
                ->whereIn('permission.name', $removals)
                ->orderBy('permission.name')->orderBy('assignment.model_type')->orderBy('assignment.model_id')
                ->get(['permission.name as permission', 'assignment.model_type', 'assignment.model_id'])
                ->map(fn (object $row): array => [
                    'permission' => (string) $row->permission,
                    'model_type' => (string) $row->model_type,
                    'model_identifier' => (string) $row->model_id,
                ])->values()->all();

            $unexpectedRoleAssignments = DB::table(config('permission.table_names.role_has_permissions').' as assignment')
                ->join(config('permission.table_names.permissions').' as permission', 'permission.id', '=', 'assignment.permission_id')
                ->join(config('permission.table_names.roles').' as role', 'role.id', '=', 'assignment.role_id')
                ->where('permission.guard_name', PermissionRegistry::GUARD)
                ->whereIn('permission.name', $removals)
                ->whereNotIn('role.name', array_keys(RoleRegistry::permissionBundles()))
                ->orderBy('permission.name')->orderBy('role.name')
                ->get(['permission.name as permission', 'role.name as role'])
                ->map(fn (object $row): array => [
                    'permission' => (string) $row->permission,
                    'role' => (string) $row->role,
                ])->values()->all();
        }

        $cmsManagerExists = Role::query()->where('name', RoleRegistry::CMS_MANAGER)
            ->where('guard_name', PermissionRegistry::GUARD)->exists();
        $cmsUsers = $cmsManagerExists
            ? User::query()->role(RoleRegistry::CMS_MANAGER)
                ->orderBy('id')->pluck('id')->map(fn (int $id): string => (string) $id)->values()->all()
            : [];

        return new FoundationRegistryAlignmentPlan(
            additions: $additions,
            removals: $removals,
            roleBundleChanges: $roleBundleChanges,
            directAssignments: $directAssignments,
            unexpectedRoleAssignments: $unexpectedRoleAssignments,
            cmsManagerUserIdentifiers: $cmsUsers,
        );
    }

    public function apply(string $reason): FoundationRegistryAlignmentPlan
    {
        $result = DB::transaction(function () use ($reason): FoundationRegistryAlignmentPlan {
            Permission::query()->where('guard_name', PermissionRegistry::GUARD)->lockForUpdate()->get();
            Role::query()->where('guard_name', PermissionRegistry::GUARD)->lockForUpdate()->get();
            $plan = $this->inspect();

            if ($plan->hasUnexpectedAssignments()) {
                throw new UnexpectedFoundationAssignmentException(
                    $plan->directAssignments,
                    $plan->unexpectedRoleAssignments,
                );
            }

            if (! $plan->hasChanges()) {
                return $plan;
            }

            $cmsUsers = User::query()->whereKey($plan->cmsManagerUserIdentifiers)
                ->lockForUpdate()->get();
            $beforeUserPermissions = $cmsUsers->mapWithKeys(
                fn (User $user): array => [(string) $user->getKey() => $user->getAllPermissions()->pluck('name')->sort()->values()->all()],
            );

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

            Permission::query()->where('guard_name', PermissionRegistry::GUARD)
                ->whereIn('name', $plan->removals)->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach ($cmsUsers as $user) {
                $user->unsetRelation('roles')->unsetRelation('permissions');
                $after = $user->getAllPermissions()->pluck('name')->sort()->values()->all();
                $before = $beforeUserPermissions->get((string) $user->getKey(), []);

                if ($before === $after) {
                    continue;
                }

                $this->audit->handle(
                    action: 'identity.role-bundle.aligned',
                    resource: $user,
                    actor: null,
                    before: ['effective_permissions' => $before],
                    after: ['effective_permissions' => $after],
                    reason: $reason,
                );
            }

            $superAdministrator = Role::query()->where('name', RoleRegistry::SUPER_ADMINISTRATOR)
                ->where('guard_name', PermissionRegistry::GUARD)->firstOrFail();
            $pageAdditions = array_values(array_intersect($plan->additions, [
                PermissionRegistry::PAGES_VIEW,
                PermissionRegistry::PAGES_CREATE,
                PermissionRegistry::PAGES_EDIT,
                PermissionRegistry::PAGES_PREVIEW,
                PermissionRegistry::PAGES_ARCHIVE,
                PermissionRegistry::PAGES_RESTORE,
            ]));
            if ($pageAdditions !== []) {
                $this->audit->handle(
                    action: 'content.permission-registry.aligned',
                    resource: $superAdministrator,
                    actor: null,
                    before: ['page_permissions' => []],
                    after: ['page_permissions' => $pageAdditions, 'cms_manager_bundle' => RoleRegistry::permissionBundles()[RoleRegistry::CMS_MANAGER]],
                    reason: $reason,
                );
            }

            $this->audit->handle(
                action: 'identity.permission-registry.aligned',
                resource: $superAdministrator,
                actor: null,
                before: [
                    'removed_permissions' => $plan->removals,
                    'role_bundle_changes' => $plan->roleBundleChanges,
                ],
                after: [
                    'registry' => PermissionRegistry::all(),
                    'role_bundles' => RoleRegistry::permissionBundles(),
                ],
                reason: $reason,
            );

            DB::afterCommit(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions());

            return new FoundationRegistryAlignmentPlan(
                additions: $plan->additions,
                removals: $plan->removals,
                roleBundleChanges: $plan->roleBundleChanges,
                directAssignments: [],
                unexpectedRoleAssignments: [],
                cmsManagerUserIdentifiers: $plan->cmsManagerUserIdentifiers,
                applied: true,
            );
        }, 3);

        return $result;
    }
}
