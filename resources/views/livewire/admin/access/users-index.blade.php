<section class="admin-access-workspace" aria-labelledby="users-results-title">
    <form class="admin-filter-panel" wire:submit.prevent aria-label="User access filters">
        <div class="admin-field admin-field-wide">
            <label for="user-search">Search name or email</label>
            <input id="user-search" type="search" wire:model.live.debounce.350ms="search" maxlength="100" placeholder="Search users">
        </div>
        <div class="admin-field">
            <label for="role-filter">Role</label>
            <select id="role-filter" wire:model.live="role">
                <option value="">All roles</option>
                @foreach ($registeredRoles as $registeredRole)
                    <option value="{{ $registeredRole }}">{{ $registeredRole }}</option>
                @endforeach
            </select>
        </div>
        <div class="admin-field">
            <label for="verification-filter">Email verification</label>
            <select id="verification-filter" wire:model.live="verification">
                <option value="">Any state</option>
                <option value="verified">Verified</option>
                <option value="unverified">Unverified</option>
            </select>
        </div>
        <div class="admin-field">
            <label for="access-filter">Administration access</label>
            <select id="access-filter" wire:model.live="administrativeAccess">
                <option value="">Any access</option>
                <option value="has">Has access</option>
                <option value="missing">No access</option>
            </select>
        </div>
        <div class="admin-field">
            <label for="sort-filter">Sort by</label>
            <select id="sort-filter" wire:model.live="sort">
                <option value="name">Name</option>
                <option value="email">Email</option>
                <option value="created_at">Creation date</option>
            </select>
        </div>
        <div class="admin-field">
            <label for="direction-filter">Direction</label>
            <select id="direction-filter" wire:model.live="direction">
                <option value="asc">Ascending</option>
                <option value="desc">Descending</option>
            </select>
        </div>
    </form>

    <div class="admin-results-heading">
        <div><p>Access directory</p><h2 id="users-results-title">Users</h2></div>
        <span>{{ $this->users->total() }} result{{ $this->users->total() === 1 ? '' : 's' }}</span>
    </div>

    @if ($this->users->isEmpty())
        <div class="admin-empty-state">
            @if (\App\Models\User::query()->doesntExist())
                <h3>No users exist</h3><p>The access directory will populate when accounts are created through approved public authentication flows.</p>
            @elseif (filled(trim($search)))
                <h3>No search results</h3><p>No users matched the supplied name or email text.</p>
            @else
                <h3>No users match these filters</h3><p>Adjust the selected access filters to broaden the result set.</p>
            @endif
        </div>
    @else
        <div class="admin-responsive-table">
            <table>
                <thead><tr><th scope="col">User</th><th scope="col">Verification</th><th scope="col">Registered roles</th><th scope="col">Admin access</th><th scope="col">Created</th><th scope="col"><span class="sr-only">Open</span></th></tr></thead>
                <tbody>
                    @foreach ($this->users as $user)
                        @php
                            $roleNames = $user->roles->pluck('name')->filter(fn ($name) => \App\Domain\Identity\Support\RoleRegistry::contains($name));
                            $hasAdmin = $roleNames->contains(fn ($name) => in_array(\App\Domain\Identity\Support\PermissionRegistry::ADMIN_ACCESS, \App\Domain\Identity\Support\RoleRegistry::permissionBundles()[$name], true));
                        @endphp
                        <tr wire:key="user-{{ $user->getKey() }}">
                            <td data-label="User"><strong>{{ $user->name }}</strong><span>{{ $user->email }}</span></td>
                            <td data-label="Verification"><span class="admin-badge">{{ $user->email_verified_at ? 'Verified' : 'Unverified' }}</span></td>
                            <td data-label="Registered roles">{{ $roleNames->isEmpty() ? 'No registered role' : $roleNames->join(', ') }}</td>
                            <td data-label="Admin access"><span class="admin-badge">{{ $hasAdmin ? 'Has access' : 'No access' }}</span></td>
                            <td data-label="Created"><time datetime="{{ $user->created_at?->toIso8601String() }}">{{ $user->created_at?->timezone('Africa/Dar_es_Salaam')->format('d M Y') }}</time></td>
                            <td data-label="Open"><a class="admin-row-link" href="{{ route('admin.access.users.show', $user) }}" aria-label="Inspect access for {{ $user->name }}">Inspect access</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="admin-pagination">{{ $this->users->links() }}</div>
    @endif
</section>
