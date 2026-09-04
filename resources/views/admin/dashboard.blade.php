<x-admin.layout title="Dashboard" description="A permission-aware overview of the administration foundation.">
    @php
        $destinations = app(\App\Domain\Admin\Navigation\AdminNavigationRegistry::class)->visibleFor(auth()->user());
        $roles = auth()->user()->getRoleNames();
    @endphp
    <section class="admin-foundation-banner" aria-labelledby="foundation-title">
        <div>
            <span class="admin-status">Foundation active</span>
            <h2 id="foundation-title">Website overview</h2>
            <p>Manage website content, media and customer Orders from the destinations available to your role.</p>
        </div>
        <div class="admin-role-context"><span>Access context</span><strong>{{ $roles->isEmpty() ? 'Permission-based access' : $roles->join(', ') }}</strong></div>
    </section>
    <section class="admin-destinations" aria-labelledby="destinations-title">
        <div class="admin-section-heading"><p>Available workspace</p><h2 id="destinations-title">Authorized destinations</h2></div>
        <div class="admin-card-grid">
            @foreach ($destinations as $destination)
                @continue($destination->routeName === 'admin.dashboard')
                <a href="{{ route($destination->routeName) }}" class="admin-destination-card">
                    <x-admin.icon :name="$destination->icon" class="admin-card-icon" />
                    <span>{{ $destination->group }}</span><h3>{{ $destination->label }}</h3>
                    <p>{{ $destination->description }}</p><strong>Open destination <span aria-hidden="true">→</span></strong>
                </a>
            @endforeach
        </div>
    </section>
</x-admin.layout>
