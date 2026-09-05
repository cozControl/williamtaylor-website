<x-admin.layout title="Navigation" description="Edit the links shown in the website header and footer." eyebrow="Website">
    <section class="admin-panel">
        <div class="admin-section-heading"><div><h2>Navigation areas</h2><p>Choose an area, update its links and save.</p></div></div>
        <div class="admin-card-grid">
            @foreach([$primary, $footer] as $resource)
                <article class="admin-card">
                    <h3>{{ $resource->title }}</h3>
                    <p>Edit labels, destinations, visibility and order.</p>
                    <div class="admin-page-actions">
                        @can('navigation.edit')<a class="admin-primary-button" href="{{ route('admin.content.navigation.edit', $resource) }}">Edit</a>@endcan
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</x-admin.layout>
