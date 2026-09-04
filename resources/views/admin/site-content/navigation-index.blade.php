<x-admin.layout title="Navigation" description="Govern independently published primary and footer navigation." eyebrow="Content">
    <x-slot:actions><span class="admin-badge">Typed resources</span></x-slot:actions>
    <section class="admin-panel">
        <div class="admin-section-heading"><div><h2>Navigation</h2><p>Primary and footer navigation are reviewed and published independently.</p></div></div>
        <div class="admin-card-grid">
            @foreach([$primary, $footer] as $resource)
                <article class="admin-card">
                    <h3>{{ $resource->title }}</h3>
                    <p>Draft revision {{ $resource->currentDraftRevision->revision_number }}.</p>
                    <div class="admin-page-actions">
                        <a class="admin-secondary-button" href="{{ route('admin.content.navigation.show', $resource) }}">Review</a>
                        @can('navigation.edit')<a class="admin-primary-button" href="{{ route('admin.content.navigation.edit', $resource) }}">Edit</a>@endcan
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</x-admin.layout>
