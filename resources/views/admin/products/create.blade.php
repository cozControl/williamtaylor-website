<x-admin.layout title="New Product" description="Create a complete storefront Product and its sellable Variants." eyebrow="Catalogue" :breadcrumbs="['Products' => route('admin.products.index'), 'New Product' => null]">
    <x-admin.flash />
    @if($errors->any())
        <div class="admin-panel" role="alert"><strong>Please correct the highlighted fields.</strong>@error('product')<p>{{ $message }}</p>@enderror</div>
    @endif
    <form method="POST" action="{{ route('admin.products.store') }}">
        @csrf
        <section class="admin-panel admin-form-grid">
            <div class="admin-section-heading"><p>Product details</p><h2>Storefront content</h2></div>
            <x-admin.field label="Product name" for="product-title" :error="$errors->first('title')"><input id="product-title" name="title" value="{{ old('title') }}" maxlength="255" @if($errors->has('title')) aria-invalid="true" aria-describedby="product-title-error" @endif></x-admin.field>
            <x-admin.field label="Slug (optional)" for="product-slug" help="Leave blank to generate from the Product name." :error="$errors->first('slug')"><input id="product-slug" name="slug" value="{{ old('slug') }}" maxlength="160" @if($errors->has('slug')) aria-invalid="true" aria-describedby="product-slug-error" @endif></x-admin.field>
            <x-admin.field label="Short description" for="product-short-description" :error="$errors->first('short_description')"><textarea id="product-short-description" name="short_description" rows="3">{{ old('short_description') }}</textarea></x-admin.field>
            <x-admin.field label="Main description" for="product-description" :error="$errors->first('description')"><textarea id="product-description" name="description" rows="7">{{ old('description') }}</textarea></x-admin.field>
            <x-admin.field label="Materials" for="product-materials" :error="$errors->first('materials')"><textarea id="product-materials" name="materials" rows="3">{{ old('materials') }}</textarea></x-admin.field>
            <x-admin.field label="Care" for="product-care" :error="$errors->first('care')"><textarea id="product-care" name="care" rows="3">{{ old('care') }}</textarea></x-admin.field>
            <x-admin.field label="Fit" for="product-fit" :error="$errors->first('fit')"><textarea id="product-fit" name="fit" rows="3">{{ old('fit') }}</textarea></x-admin.field>
            <x-admin.field label="Features" for="product-features" help="Enter one feature per line." :error="$errors->first('features')"><textarea id="product-features" name="features" rows="4">{{ old('features') }}</textarea></x-admin.field>
        </section>
        <section class="admin-panel admin-form-grid">
            <div class="admin-section-heading"><p>Pricing</p><h2>Authoritative Product price</h2><p>Variants use the Product price unless you set an override after creation.</p></div>
            <label for="product-currency">Currency<select id="product-currency" name="currency"><option value="TZS">TZS</option></select></label>
            <x-admin.field label="Base price (TZS)" for="product-base-price" :error="$errors->first('base_price')"><input id="product-base-price" name="base_price" inputmode="numeric" value="{{ old('base_price') }}" placeholder="185,000" @if($errors->has('base_price')) aria-invalid="true" aria-describedby="product-base-price-error" @endif></x-admin.field>
            <x-admin.field label="Compare-at price (optional)" for="product-compare-price" :error="$errors->first('compare_at_price')"><input id="product-compare-price" name="compare_at_price" inputmode="numeric" value="{{ old('compare_at_price') }}"></x-admin.field>
        </section>
        <section class="admin-panel admin-form-grid">
            <div class="admin-section-heading"><p>Categories</p><h2>Storefront classification</h2></div>
            <x-admin.field label="Primary Category" for="product-primary-category" :error="$errors->first('primary_category_id')"><select id="product-primary-category" name="primary_category_id" @if($errors->has('primary_category_id')) aria-invalid="true" aria-describedby="product-primary-category-error" @endif><option value="">Choose Category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('primary_category_id') === $category->id)>{{ $category->parent?->name ? $category->parent->name.' → ' : '' }}{{ $category->name }} ({{ $category->collection->currentDraftRevision?->title ?? $category->collection->slug }})</option>@endforeach</select></x-admin.field>
            <fieldset><legend>Additional Categories</legend>@foreach($categories as $category)<label><input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, old('category_ids', []), true))> {{ $category->name }} ({{ $category->collection->currentDraftRevision?->title ?? $category->collection->slug }})</label>@endforeach</fieldset>
        </section>
        <section class="admin-panel">
            <div class="admin-section-heading"><p>Media & colours</p><h2>Primary image and gallery</h2><p>Only ready images are selectable. Removing an association preserves the Media Asset.</p></div>
            <div class="admin-form-grid">
                <div><h3>Primary image</h3><x-admin.media-picker id="product-create-primary" name="primary_media_id" :selected="$primarySelected" button-label="Choose media" :show-alt="true" :alts="old('media_alt', [])" :error="$errors->first('primary_media_id') ?: collect($errors->get('media_alt.*'))->flatten()->first()" /></div>
                <div class="admin-form-wide"><h3>Gallery</h3><x-admin.media-picker id="product-create-gallery" name="gallery_media_ids" mode="multiple" :selected="$gallerySelected" button-label="Add images" :show-order="true" :show-alt="true" :orders="old('gallery_order', [])" :alts="old('media_alt', [])" :error="$errors->first('gallery_media_ids')" /></div>
            </div>
        </section>
        <section class="admin-panel">
            <x-admin.product-draft-options :colours="$draftColours" :sizes="$draftSizes" :variants="$draftVariants" :default-key="old('default_variant_key', '')" :errors="$errors->toArray()" />
        </section>
        <section class="admin-panel admin-form-grid">
            <div class="admin-section-heading"><p>Status</p><h2>Storefront availability</h2></div>
            <label for="product-status">Status<select id="product-status" name="status"><option value="hidden" @selected(old('status', 'hidden') === 'hidden')>Hidden</option><option value="active" @selected(old('status') === 'active')>Active</option></select></label>
            <div><strong>Readiness</strong><p>Active Products need a primary image, a price, a primary Category, and a default Variant. Save as Hidden while any item is missing.</p></div>
        </section>
        <x-admin.form-actions sticky>
            <x-slot:secondary><a class="admin-secondary-button" href="{{ route('admin.products.index') }}">Back to Products</a></x-slot:secondary>
            <x-slot:primary><button class="admin-primary-button">Save Product</button></x-slot:primary>
        </x-admin.form-actions>
    </form>
</x-admin.layout>
