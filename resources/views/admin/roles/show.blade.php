<x-admin.layout :title="$role" description="Read-only inspection of a registered role and its effective bundle." :breadcrumbs="['Dashboard' => route('admin.dashboard'), 'Roles' => route('admin.access.roles.index'), $role => null]">
    <div class="admin-access-detail">
        @if ($drift)
            <section class="admin-warning" aria-labelledby="role-drift-title">
                <h2 id="role-drift-title">Role registry drift detected</h2>
                <p>The database state differs from the code-owned bundle. Run <code>php artisan rbac:audit</code>. This page will not modify or normalize it.</p>
            </section>
        @endif
        <section class="admin-panel">
            <div class="admin-role-title"><span class="admin-badge">{{ $metadata['risk'] }} risk</span><h2>{{ $role }}</h2></div>
            <p>{{ $metadata['description'] }}</p>
            <dl class="admin-definition-grid">
                <div><dt>Registry ownership</dt><dd>Code-owned</dd></div>
                <div><dt>Assigned users</dt><dd>{{ $userCount }}</dd></div>
                <div><dt>Authorization behavior</dt><dd>{{ $role === \App\Domain\Identity\Support\RoleRegistry::SUPER_ADMINISTRATOR ? 'Monitored bypass for registered permissions' : 'Explicit registered bundle' }}</dd></div>
            </dl>
            <a class="admin-row-link" href="{{ route('admin.access.users.index', ['role' => $role]) }}">View users assigned this role</a>
        </section>
        <section class="admin-panel" aria-labelledby="role-permissions-title">
            <div class="admin-section-heading"><p>Registered bundle</p><h2 id="role-permissions-title">Permissions by business area</h2></div>
            @foreach ($permissions->groupBy(fn ($permission) => $permissionMetadata[$permission]['area']) as $area => $areaPermissions)
                <div class="admin-permission-group"><h3>{{ $area }}</h3><div class="admin-permission-list">
                    @foreach ($areaPermissions as $permission)
                        <article class="is-granted"><span class="admin-badge">Included</span><h4>{{ $permissionMetadata[$permission]['label'] }}</h4><p>{{ $permissionMetadata[$permission]['description'] }}</p></article>
                    @endforeach
                </div></div>
            @endforeach
        </section>
        @if ($role === \App\Domain\Identity\Support\RoleRegistry::SUPER_ADMINISTRATOR)
            <section class="admin-warning"><h2>Safety invariant</h2><p>The final Super Administrator cannot lose this role. The monitored bypass cannot override that invariant.</p></section>
        @endif
        <details class="admin-panel admin-developer-details"><summary>Developer details</summary><dl><div><dt>Raw role name</dt><dd>{{ $role }}</dd></div><div><dt>Raw permission names</dt><dd>{{ $permissions->join(', ') }}</dd></div></dl></details>
    </div>
</x-admin.layout>
