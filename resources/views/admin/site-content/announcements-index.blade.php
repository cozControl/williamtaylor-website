<x-admin.layout title="Announcements" description="Manage storefront announcement text, links and active dates." eyebrow="Website">
    <div class="admin-page-actions">@can('announcements.create')<a class="admin-primary-button" href="{{ route('admin.content.announcements.create') }}">Create announcement</a>@endcan</div>
    <section class="admin-panel">
        <form method="get" class="admin-filter-grid">
            <label>Search<input name="search" value="{{ request('search') }}"></label>
            <label>Lifecycle<select name="lifecycle"><option value="">All</option><option value="active" @selected(request('lifecycle') === 'active')>Active</option><option value="archived" @selected(request('lifecycle') === 'archived')>Archived</option></select></label>
            <label>Schedule<select name="schedule"><option value="">All</option><option value="scheduled" @selected(request('schedule') === 'scheduled')>Scheduled</option></select></label>
            <button class="admin-secondary-button">Filter</button>
        </form>
        <div class="admin-table-wrap"><table><thead><tr><th>Title</th><th>Status</th><th>Starts</th><th>Actions</th></tr></thead><tbody>
            @forelse($announcements as $announcement)<tr><td>{{ $announcement->title }}</td><td>{{ $announcement->archived_at ? 'Archived' : 'Active' }}</td><td>{{ $announcement->publicationState?->scheduled_for?->timezone('Africa/Dar_es_Salaam')->format('Y-m-d H:i') ?? 'Immediately' }}</td><td>@can('announcements.edit')<a href="{{ route('admin.content.announcements.edit', $announcement) }}">Edit</a>@else<a href="{{ route('admin.content.announcements.show', $announcement) }}">View</a>@endcan</td></tr>
            @empty<tr><td colspan="4">No announcements match the filters.</td></tr>@endforelse
        </tbody></table></div>
        {{ $announcements->links() }}
    </section>
</x-admin.layout>
