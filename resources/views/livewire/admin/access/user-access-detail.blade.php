<div class="admin-access-detail">
    <section class="admin-panel admin-identity-card" aria-labelledby="identity-title">
        <div class="admin-section-heading"><p>Account identity</p><h2 id="identity-title">{{ $this->target->name }}</h2></div>
        <dl class="admin-definition-grid">
            <div><dt>Login email</dt><dd>{{ $this->target->email }}</dd></div>
            <div><dt>Verification</dt><dd>{{ $this->target->email_verified_at ? 'Verified' : 'Unverified' }}</dd></div>
            <div><dt>Created</dt><dd>{{ $this->target->created_at?->timezone('Africa/Dar_es_Salaam')->format('d M Y, H:i') }}</dd></div>
        </dl>
        @if (auth()->id() === $this->target->getKey())
            <div class="admin-warning"><strong>You are managing your own access.</strong><p>A change that removes your administration access will be blocked.</p></div>
        @endif
    </section>

    @if ($this->access->warnings)
        <section class="admin-warning" aria-labelledby="access-warning-title">
            <h2 id="access-warning-title">Access registry warning</h2>
            <ul>@foreach ($this->access->warnings as $warning)<li>{{ $warning }}</li>@endforeach</ul>
        </section>
    @endif

    <section class="admin-panel" aria-labelledby="current-roles-title">
        <div class="admin-section-heading"><p>Current roles</p><h2 id="current-roles-title">Registered assignments</h2></div>
        <div class="admin-role-grid">
            @foreach ($roles as $roleName => $metadata)
                <article class="admin-role-card">
                    <span class="admin-badge">{{ in_array($roleName, $this->access->assignedRoles, true) ? 'Assigned' : 'Not assigned' }}</span>
                    <h3>{{ $roleName }}</h3><p>{{ $metadata['description'] }}</p><small>Code-owned role</small>
                </article>
            @endforeach
        </div>
    </section>

    <section class="admin-panel" aria-labelledby="effective-access-title">
        <div class="admin-section-heading"><p>Effective access</p><h2 id="effective-access-title">Administrative capabilities</h2></div>
        @foreach (collect($permissions)->groupBy('area') as $area => $areaPermissions)
            <div class="admin-permission-group"><h3>{{ $area }}</h3><div class="admin-permission-list">
                @foreach ($areaPermissions as $slug => $metadata)
                    @php $granted = in_array($slug, $this->access->effectivePermissions, true); @endphp
                    <article class="{{ $granted ? 'is-granted' : '' }}">
                        <span class="admin-badge">{{ $granted ? 'Allowed' : 'Not allowed' }}</span>
                        <h4>{{ $metadata['label'] }}</h4><p>{{ $metadata['description'] }}</p>
                        @if ($granted)<small>Source: {{ implode(', ', $this->access->permissionSources[$slug] ?? []) }}</small>@endif
                    </article>
                @endforeach
            </div></div>
        @endforeach
    </section>

    @if (auth()->user()->can(\App\Domain\Identity\Support\PermissionRegistry::USERS_MANAGE) && auth()->user()->can(\App\Domain\Identity\Support\PermissionRegistry::ROLES_MANAGE))
        <section class="admin-panel admin-mutation-panel" data-admin-mutation aria-labelledby="change-access-title">
            <div class="admin-section-heading"><p>Proposed access change</p><h2 id="change-access-title">Assign or revoke a registered role</h2></div>
            <div id="access-feedback" aria-live="assertive" role="status" tabindex="-1" x-on:access-preview-stale.window="$nextTick(() => $el.focus())" class="admin-feedback {{ $feedbackType === 'error' ? 'is-error' : 'is-success' }}" @if (!$feedback) hidden @endif>{{ $feedback }}</div>
            @if ($errors->any())
                <div class="admin-error-summary" role="alert" tabindex="-1"><strong>Review the highlighted fields.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form wire:submit="previewChange" class="admin-change-form">
                <div class="admin-field"><label for="operation">Change type</label><select id="operation" wire:model="operation"><option value="">Select change</option><option value="assign">Assign role</option><option value="revoke">Revoke role</option></select></div>
                <div class="admin-field"><label for="selected-role">Registered role</label><select id="selected-role" wire:model="selectedRole"><option value="">Select role</option>@foreach (array_keys($roles) as $roleName)<option value="{{ $roleName }}">{{ $roleName }}</option>@endforeach</select></div>
                <div class="admin-field admin-field-wide"><label for="reason">Reason for change</label><textarea id="reason" wire:model="reason" maxlength="2000" rows="4" required></textarea></div>
                <button class="admin-primary-button" type="submit">Preview change</button>
            </form>
            @if ($preview)
                <div class="admin-preview" aria-labelledby="preview-title">
                    <h3 id="preview-title">Authoritative server preview</h3>
                    @if ($preview['superAdministratorChanges'])<div class="admin-warning"><strong>Elevated role change</strong><p>This proposal changes Super Administrator status and receives additional safety enforcement.</p></div>@endif
                    @if ($preview['administrativeAccessChanges'])
                        <div class="admin-warning"><strong>Administrative access changes</strong><p>{{ $preview['willHaveAdministrativeAccess'] ? 'This account will gain administration access.' : 'This account will lose administration access.' }}</p></div>
                    @elseif ($preview['willHaveAdministrativeAccess'])
                        <p class="admin-feedback is-success">Administration access is retained by the proposed state.</p>
                    @endif
                    <div class="admin-comparison">
                        <div><h4>Current roles</h4><p>{{ implode(', ', $preview['currentRoles']) ?: 'No registered roles' }}</p></div>
                        <div><h4>Proposed roles</h4><p>{{ implode(', ', $preview['proposedRoles']) ?: 'No registered roles' }}</p></div>
                        <div><h4>Permissions gained</h4><ul>@forelse ($preview['gainedPermissions'] as $permission)<li>{{ $permissions[$permission]['label'] }}</li>@empty<li>None</li>@endforelse</ul></div>
                        <div><h4>Permissions lost</h4><ul>@forelse ($preview['lostPermissions'] as $permission)<li>{{ $permissions[$permission]['label'] }}</li>@empty<li>None</li>@endforelse</ul></div>
                    </div>
                    <label class="admin-confirmation"><input type="checkbox" wire:model="confirmed"> I confirm this proposed access change.</label>
                    <button class="admin-danger-button" type="button" wire:click="applyChange">Apply confirmed change</button>
                </div>
            @endif
        </section>
    @endif

    <details class="admin-panel admin-developer-details">
        <summary>Developer details</summary>
        <dl><div><dt>Internal user identifier</dt><dd>{{ $this->target->getKey() }}</dd></div><div><dt>Raw roles</dt><dd>{{ implode(', ', $this->access->assignedRoles) ?: 'None' }}</dd></div><div><dt>Raw permissions</dt><dd>{{ implode(', ', $this->access->effectivePermissions) ?: 'None' }}</dd></div></dl>
    </details>
</div>
