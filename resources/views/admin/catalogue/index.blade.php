<x-admin.layout title="Catalogue" description="Manage Products, Categories, Collections and sellable Variants." eyebrow="Catalogue" :breadcrumbs="['Catalogue' => null]">
    <x-slot:actions>@can('products.manage')<a class="admin-primary-button" href="{{ route('admin.products.create') }}">New Product</a>@endcan</x-slot:actions>

    <div class="catalogue-overview">
    <div class="admin-section-heading"><p>Catalogue overview</p><h2>Catalogue health at a glance</h2></div>
    <section class="catalogue-metrics" aria-label="Catalogue summary">
        <article><span>Products</span><strong>{{ $metrics['products'] }}</strong><small>Canonical, non-archived Products</small></article>
        <article><span>Active Products</span><strong>{{ $metrics['active_products'] }}</strong><small>Visible storefront Products</small></article>
        <article><span>Categories</span><strong>{{ $metrics['categories'] }}</strong><small>Non-archived Categories</small></article>
        <article><span>Collections</span><strong>{{ $metrics['collections'] }}</strong><small>Storefront ranges and edits</small></article>
        <article><span>Variants</span><strong>{{ $metrics['variants'] }}</strong><small>Variants of active catalogue records</small></article>
        <article class="catalogue-metric-attention"><span>Needs attention</span><strong>{{ $metrics['needs_attention'] }}</strong><small>Missing content, price, category, image or sellable Variant</small>@if($metrics['needs_attention'] > 0)<a href="{{ route('admin.products.index') }}">Manage Products</a>@endif</article>
    </section>

    <section class="catalogue-management"><div class="admin-section-heading"><p>Catalogue management</p><h2>Manage your catalogue</h2></div><div class="catalogue-shortcuts">
        <article class="catalogue-shortcut"><span>Products</span><h3>Product workspace</h3><p>Manage Product information, pricing, Media, Colours, Sizes and Variants.</p><a class="admin-secondary-button" href="{{ route('admin.products.index') }}">Manage Products</a></article>
        <article class="catalogue-shortcut"><span>Categories</span><h3>Category structure</h3><p>Manage structural Product classification.</p><a class="admin-secondary-button" href="{{ route('admin.product-categories.index') }}">Manage Categories</a></article>
        <article class="catalogue-shortcut"><span>Collections</span><h3>Curated ranges</h3><p>Manage curated storefront Product groups and their ordering.</p><a class="admin-secondary-button" href="{{ route('admin.collections.index') }}">Manage Collections</a></article>
    </div></section>
    </div>
</x-admin.layout>
