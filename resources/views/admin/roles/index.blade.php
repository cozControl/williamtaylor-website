<x-admin.layout title="Roles" description="Inspect the code-owned role catalogue and registered permission bundles." :breadcrumbs="['Dashboard' => route('admin.dashboard'), 'Roles' => null]">
    <section class="admin-access-workspace" aria-labelledby="role-catalogue-title">
        <div class="admin-results-heading"><div><p>Read-only registry</p><h2 id="role-catalogue-title">Registered roles</h2></div><span>{{ count($roles) }} roles</span></div>
        <div class="admin-role-catalogue">
            @foreach ($roles as $role => $metadata)
                <article class="admin-panel">
                    <div class="admin-role-title"><span class="admin-badge">{{ $metadata['risk'] }} risk</span><h3>{{ $role }}</h3></div>
                    <p>{{ $metadata['description'] }}</p>
                    <dl class="admin-definition-grid">
                        <div><dt>Registered permissions</dt><dd>{{ count($bundles[$role]) }}</dd></div>
                        <div><dt>Assigned users</dt><dd>{{ $counts[$role] ?? 0 }}</dd></div>
                        <div><dt>Authorization</dt><dd>{{ $role === \App\Domain\Identity\Support\RoleRegistry::SUPER_ADMINISTRATOR ? 'Monitored bypass' : 'Role bundle' }}</dd></div>
                    </dl>
                    <a class="admin-row-link" href="{{ route('admin.access.roles.show', ['role' => $role]) }}">Inspect role</a>
                </article>
            @endforeach
        </div>
    </section>
</x-admin.layout>
