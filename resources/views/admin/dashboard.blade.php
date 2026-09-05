<x-admin.layout title="Dashboard" description="Manage your William Taylor catalogue, website and customer Orders.">
    @php
        $destinations = app(\App\Domain\Admin\Navigation\AdminNavigationRegistry::class)->visibleFor(auth()->user());
        $roles = auth()->user()->getRoleNames();
    @endphp
    <section class="admin-foundation-banner" aria-labelledby="foundation-title">
        <div>
            <span class="admin-status">Administration</span>
            <h2 id="foundation-title">William Taylor overview</h2>
            <p>Open the catalogue, website and Order destinations available to your role.</p>
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
