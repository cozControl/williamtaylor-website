<x-admin.layout title="Products" description="Manage products, pricing, imagery and variants." eyebrow="Catalogue" :breadcrumbs="['Products' => null]">
    <x-slot:actions>
        @can('products.manage')
            <a class="admin-primary-button" href="{{ route('admin.products.create') }}">New Product</a>
        @endcan
    </x-slot:actions>

    <form method="GET" class="admin-panel catalogue-filters" aria-label="Product filters" aria-labelledby="product-filters-title">
        <h2 id="product-filters-title">Filters</h2>
        <div class="catalogue-filter-grid">
            <label><span>Search products</span><input name="search" value="{{ $search }}" placeholder="Name or slug"></label>
            <label>
                <span>Status</span>
                <select name="status">
                    <option value="all">All statuses</option>
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="hidden" @selected($status === 'hidden')>Hidden</option>
                    <option value="archived" @selected($status === 'archived')>Archived</option>
                </select>
            </label>
            <label>
                <span>Category</span>
                <select name="category">
                    <option value="">All categories</option>
                    @foreach($categories as $item)
                        <option value="{{ $item->id }}" @selected($category === $item->id)>{{ $item->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="admin-form-actions catalogue-filter-actions">
            <div class="admin-form-actions-secondary"><a class="admin-secondary-button" href="{{ route('admin.products.index') }}">Clear</a></div>
            <div class="admin-form-actions-primary"><button class="admin-primary-button">Apply filters</button></div>
        </div>
    </form>

    <section class="admin-panel catalogue-products-panel" aria-label="Products">
        <div class="admin-table-wrap catalogue-product-table">
            <table>
                <colgroup><col class="product-column"><col class="category-column"><col class="price-column"><col class="status-column"><col class="variants-column"><col class="updated-column"><col class="actions-column"></colgroup>
                <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Status</th><th>Variants</th><th>Updated</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse($products as $product)
                    @php
                        $primaryCategory = $product->categories->firstWhere('pivot.is_primary', true) ?? $product->categories->first();
                        $isPublic = (bool) $storefrontResolvable->get($product->id, false);
                        $editUrl = route('admin.products.edit', $product);
                    @endphp
                    <tr>
                        <td data-label="Product">
                            <div class="catalogue-product-identity">
                                <div class="catalogue-product-thumbnail">
                                    @if($primary->get($product->id))
                                        <img src="{{ $primary->get($product->id) }}" alt="">
                                    @else
                                        <span>No image</span>
                                    @endif
                                </div>
                                <div><a href="{{ $editUrl }}"><strong>{{ $product->currentDraftRevision?->title ?? 'Untitled Product' }}</strong></a><small>/{{ $product->slug }}</small></div>
                            </div>
                        </td>
                        <td data-label="Category">{{ $primaryCategory?->name ?? 'Uncategorized' }}</td>
                        <td data-label="Price" class="catalogue-product-price">{{ app(\App\Domain\Catalogue\Support\ProductPrice::class)->format($product->base_price_minor, $product->currency) ?? 'Not set' }}</td>
                        <td data-label="Status"><span class="catalogue-status-badge is-{{ $product->archived_at ? 'archived' : ($product->catalogue_status === 'ready' ? 'active' : 'hidden') }}">{{ $product->archived_at ? 'Archived' : ($product->catalogue_status === 'ready' ? 'Active' : 'Hidden') }}</span></td>
                        <td data-label="Variants">{{ $product->variants_count }}</td>
                        <td data-label="Updated">{{ $product->updated_at->diffForHumans() }}</td>
                        <td data-label="Actions">
                            <div class="catalogue-row-actions">
                                <a class="admin-secondary-button" href="{{ $editUrl }}">Edit</a>
                                @if($isPublic)
                                    <a href="{{ route('products.show', ['product' => $product->slug]) }}" target="_blank" rel="noopener">View storefront</a>
                                @endif
                                @can('products.manage')
                                    @unless($product->archived_at)
                                        <form method="POST" action="{{ route('admin.products.archive', $product) }}" onsubmit="return confirm('Delete this product? It will be removed from the storefront. Existing orders and history are preserved.');">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="admin-danger-button">Delete</button>
                                        </form>
                                    @endunless
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="catalogue-empty-state">No Products match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="catalogue-pagination">{{ $products->links() }}</div>
    </section>
</x-admin.layout>
