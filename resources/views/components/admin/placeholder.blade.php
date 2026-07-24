@props(['title', 'purpose', 'future'])
<section class="admin-panel admin-placeholder" data-admin-workspace>
    <span class="admin-status">Foundation destination</span>
    <h2>{{ $title }} is not active in this phase</h2>
    <p>{{ $purpose }}</p>
    <div class="admin-placeholder-note">
        <strong>What comes later</strong>
        <p>{{ $future }}</p>
    </div>
    <a class="admin-text-link" href="{{ route('admin.dashboard') }}">Return to dashboard</a>
</section>
