<x-admin.layout :title="$collection->exists ? 'Edit '.$collection->currentDraftRevision->title : 'New Collection'" description="Group Products for storefront ranges and edits." eyebrow="Catalogue" :breadcrumbs="['Catalogue' => route('admin.catalogue.index'), 'Collections' => route('admin.collections.index'), ($collection->exists ? $collection->currentDraftRevision->title : 'New Collection') => null]">
    <div class="collection-editor-shell">
        @if ($collection->exists)
            <div class="collection-editor-summary" data-collection-editor-summary>
                <div>
                    <span class="admin-status-label">{{ $collection->catalogue_status === 'ready' ? 'Visible' : 'Hidden' }}</span>
                    <span>{{ $collection->products->count() }} {{ Str::plural('Product', $collection->products->count()) }}</span>
                </div>
                @if ($collection->catalogue_status === 'ready')
                    <a class="admin-secondary-button" href="{{ route('collections.show', $collection) }}" target="_blank" rel="noopener">View storefront</a>
                @endif
            </div>
        @endif

        <x-admin.flash :errors="$errors" />

        <form method="POST" class="collection-editor" action="{{ $collection->exists ? route('admin.collections.update', $collection) : route('admin.collections.store') }}">
            @csrf
            @if ($collection->exists)
                @method('PUT')
            @endif

            <section class="admin-panel collection-editor-section admin-form-grid" data-collection-section="details">
                <div class="admin-section-heading"><p>Collection details</p><h2>Details and visibility</h2><span>Set the customer-facing name, URL and description.</span></div>
                <x-admin.field label="Name" for="collection-name" :error="$errors->first('name')"><input id="collection-name" name="name" required value="{{ old('name', $collection->currentDraftRevision?->title) }}"></x-admin.field>
                <x-admin.field label="Slug" for="collection-slug" help="Generated from the name when left blank." :error="$errors->first('slug')"><input id="collection-slug" name="slug" value="{{ old('slug', $collection->slug) }}"></x-admin.field>
                <x-admin.field class="admin-form-wide" label="Description" for="collection-description" help="Summarise this curated storefront range." :error="$errors->first('description')"><textarea id="collection-description" name="description" required rows="5">{{ old('description', $collection->currentDraftRevision?->short_description) }}</textarea></x-admin.field>
                <x-admin.field class="collection-visibility-field" label="Visibility" for="collection-visibility" help="Hidden Collections do not appear on the storefront." :error="$errors->first('visibility')"><select id="collection-visibility" name="visibility"><option value="hidden" @selected(old('visibility', $collection->catalogue_status === 'ready' ? 'visible' : 'hidden') === 'hidden')>Hidden</option><option value="visible" @selected(old('visibility', $collection->catalogue_status === 'ready' ? 'visible' : 'hidden') === 'visible')>Visible</option></select></x-admin.field>
            </section>

            <section class="admin-panel collection-editor-section" data-collection-section="media">
                <div class="admin-section-heading"><p>Collection image</p><h2>Storefront image</h2><span>Choose one ready image and provide meaningful alternative text.</span></div>
                <div class="collection-media-composition">
                    <x-admin.media-picker id="collection-media" name="media_asset_id" :selected="$selectedMedia" button-label="Choose media" />
                    @error('media_asset_id')<small class="admin-field-error" role="alert">{{ $message }}</small>@enderror
                    <x-admin.field label="Alt text" for="collection-media-alt" help="A contextual override. When blank, the Media Asset default is used." :error="$errors->first('media_alt')"><input id="collection-media-alt" name="media_alt" value="{{ $mediaAlt }}"></x-admin.field>
                    <p class="admin-field-help">Removing this usage preserves the Media Asset in the Media Library.</p>
                </div>
            </section>

            <section class="admin-panel collection-editor-section" data-collection-section="products">
                <div class="admin-section-heading"><p>Products</p><h2>Select and order Products</h2><span>Choose the Products shown in this Collection and control their storefront order.</span></div>
                <x-admin.field label="Search Products" for="collection-product-search" help="Filter available Products by name or slug."><input id="collection-product-search" type="search" placeholder="Search Products" data-collection-product-search></x-admin.field>
                <fieldset class="collection-product-fieldset">
                    <legend>Selected Products</legend>
                    <p class="admin-field-help">Select a Product, then set its display order. Lower numbers appear first.</p>
                    <div class="collection-product-choices" data-collection-product-list>
                        @forelse ($products as $product)
                            @php($membership = $collection->products->firstWhere('product_id', $product->id))
                            <div class="collection-product-choice" data-product-search="{{ str(($product->currentDraftRevision?->title ?? '').' '.$product->slug)->lower() }}">
                                <input id="collection-product-{{ $product->id }}" type="checkbox" name="product_ids[]" value="{{ $product->id }}" @checked(in_array($product->id, old('product_ids', $collection->products->pluck('product_id')->all()), true))>
                                <label for="collection-product-{{ $product->id }}"><strong>{{ $product->currentDraftRevision?->title ?? $product->slug }}</strong><small>{{ $product->slug }}</small></label>
                                <x-admin.field label="Display order" for="collection-order-{{ $product->id }}" help="Lower numbers appear first."><input id="collection-order-{{ $product->id }}" type="number" min="0" max="999" name="product_order[{{ $product->id }}]" value="{{ old('product_order.'.$product->id, $membership?->position ?? 999) }}"></x-admin.field>
                            </div>
                        @empty
                            <p>No canonical Products exist. Run <code>php artisan catalogue:bootstrap-oxford --user=OWNER_EMAIL</code> with the intended owner account.</p>
                        @endforelse
                    </div>
                </fieldset>
            </section>

            <x-admin.form-actions sticky>
                <x-slot:secondary>
                    <a class="admin-secondary-button" href="{{ route('admin.collections.index') }}">Back to Collections</a>
                </x-slot:secondary>
                <x-slot:primary>
                    <button class="admin-primary-button">{{ $collection->exists ? 'Save changes' : 'Save Collection' }}</button>
                </x-slot:primary>
            </x-admin.form-actions>
        </form>
    </div>

    <script>
        (() => {
            const search = document.querySelector('[data-collection-product-search]');
            if (!search) return;
            search.addEventListener('input', () => {
                const query = search.value.trim().toLowerCase();
                document.querySelectorAll('[data-product-search]').forEach((row) => {
                    row.hidden = query !== '' && !row.dataset.productSearch.includes(query);
                });
            });
        })();
    </script>
</x-admin.layout>
