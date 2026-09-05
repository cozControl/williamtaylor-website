<x-admin.layout title="Catalogue" description="Manage Products, Categories, Variants and catalogue readiness." eyebrow="Catalogue" :breadcrumbs="['Catalogue' => null]">
    <div class="admin-page-actions">
        @can('products.manage')<a class="admin-primary-button" href="{{ route('admin.products.create') }}">New Product</a>@endcan
        <a class="admin-secondary-button" href="{{ route('admin.products.index') }}">Manage Products</a>
        <a class="admin-secondary-button" href="{{ route('admin.product-categories.index') }}">Manage Categories</a>
        <a class="admin-secondary-button" href="{{ route('admin.collections.index') }}">Manage Collections</a>
    </div>

    <section class="catalogue-metrics" aria-label="Catalogue summary">
        <article><span>Products</span><strong>{{ $metrics['products'] }}</strong><small>Canonical, non-archived Products</small></article>
        <article><span>Active Products</span><strong>{{ $metrics['active_products'] }}</strong><small>Visible storefront Products</small></article>
        <article><span>Categories</span><strong>{{ $metrics['categories'] }}</strong><small>Non-archived Categories</small></article>
        <article><span>Collections</span><strong>{{ $metrics['collections'] }}</strong><small>Storefront ranges and edits</small></article>
        <article><span>Variants</span><strong>{{ $metrics['variants'] }}</strong><small>Variants of active catalogue records</small></article>
        <article><span>Needs attention</span><strong>{{ $metrics['needs_attention'] }}</strong><small>Missing content, price, category, image or sellable Variant</small></article>
    </section>

    <section class="admin-destinations"><div class="admin-section-heading"><p>Workspaces</p><h2>Catalogue shortcuts</h2></div><div class="admin-card-grid catalogue-shortcuts">
        <a class="admin-destination-card" href="{{ route('admin.products.index') }}"><span>Products</span><h3>Manage Products</h3><p>Manage Product information, pricing, images, options and Variants.</p></a>
        <a class="admin-destination-card" href="{{ route('admin.product-categories.index') }}"><span>Categories</span><h3>Manage Categories</h3><p>Organize Products for storefront navigation and merchandising.</p></a>
        <a class="admin-destination-card" href="{{ route('admin.collections.index') }}"><span>Collections</span><h3>Manage Collections</h3><p>Group and order Products for storefront ranges and featured edits.</p></a>
    </div></section>
</x-admin.layout>
