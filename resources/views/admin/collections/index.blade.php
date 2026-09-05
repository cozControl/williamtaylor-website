<x-admin.layout title="Collections" description="Group and order Products for storefront ranges and featured edits." eyebrow="Catalogue" :breadcrumbs="['Catalogue' => route('admin.catalogue.index'), 'Collections' => null]">
    <x-admin.flash :errors="$errors" />
    <div class="admin-page-actions">
        @can('products.manage')
            <a class="admin-primary-button" href="{{ route('admin.collections.create') }}">New Collection</a>
        @endcan
    </div>
    <form method="GET" class="admin-panel catalogue-filters">
        <label><span>Search Collections</span><input name="search" value="{{ $search }}" placeholder="Collection name"></label>
        <label><span>Visibility</span><select name="status"><option value="all">All</option><option value="visible" @selected($status === 'visible')>Visible</option><option value="hidden" @selected($status === 'hidden')>Hidden</option><option value="archived" @selected($status === 'archived')>Archived</option></select></label>
        <div class="admin-page-actions"><button class="admin-primary-button">Apply filters</button><a class="admin-secondary-button" href="{{ route('admin.collections.index') }}">Clear</a></div>
    </form>
    @if($collections->isEmpty())
        <section class="admin-panel admin-empty-state">
            <h2>No Collections yet</h2>
            <p>Create your first Collection to group Products for the storefront.</p>
            @can('products.manage')
                <a class="admin-primary-button" href="{{ route('admin.collections.create') }}">New Collection</a>
            @endcan
        </section>
    @else
        <section class="admin-panel">
            <div class="admin-table-wrap catalogue-product-table"><table>
                <thead><tr><th>Image</th><th>Collection</th><th>Products</th><th>Visibility</th><th>Updated</th><th>Actions</th></tr></thead>
                <tbody>
                @foreach($collections as $item)
                <tr>
                    <td data-label="Image">
                        @if($images->get($item->id))
                            <img src="{{ $images->get($item->id) }}" alt="Thumbnail for {{ $item->currentDraftRevision?->title }}">
                        @else
                            &mdash;
                        @endif
                    </td>
                    <td data-label="Collection">
                        @if(!$item->archived_at)
                            <a href="{{ route('admin.collections.edit', $item) }}"><strong>{{ $item->currentDraftRevision?->title }}</strong></a>
                        @else
                            <strong>{{ $item->currentDraftRevision?->title }}</strong>
                        @endif
                        <br><small>/collections/{{ $item->slug }}</small>
                    </td>
                    <td data-label="Products">{{ $item->products_count }} {{ Str::plural('Product', $item->products_count) }}</td>
                    <td data-label="Visibility">{{ $item->archived_at ? 'Archived' : ($item->catalogue_status === 'ready' ? 'Visible' : 'Hidden') }}</td>
                    <td data-label="Updated">{{ $item->updated_at->diffForHumans() }}</td>
                    <td data-label="Actions">
                        <div class="admin-page-actions">
                            @if(!$item->archived_at)
                                @can('products.manage')
                                    <a class="admin-secondary-button" href="{{ route('admin.collections.edit', $item) }}">Edit</a>
                                @endcan
                                @if($item->catalogue_status === 'ready')
                                    <a href="{{ route('collections.show', $item) }}" target="_blank" rel="noopener">View storefront</a>
                                @endif
                            @else
                                <span>Read only</span>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table></div>
            {{ $collections->links() }}
        </section>
    @endif
</x-admin.layout>
