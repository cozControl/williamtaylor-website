<x-admin.layout title="Women's Handbags" eyebrow="Homepage" description="Manage the editorial hero and curated Products in this Homepage section." :breadcrumbs="['Homepage' => route('admin.homepage.edit'), 'Women\'s Handbags' => null]">
    <x-admin.flash :errors="$errors" />
    <x-admin.homepage-section-visibility section-key="handbags" />
    <style>
        .homepage-handbags-editor .homepage-explore-collection-summary {grid-template-columns:5.5rem minmax(0,1fr)}
        .homepage-handbags-editor .homepage-explore-collection-summary > img {width:5.5rem}
    </style>
    <div class="admin-page-actions"><a class="admin-secondary-button" href="{{ route('home') }}" target="_blank" rel="noopener">View homepage</a></div>
    <form method="POST" action="{{ route('admin.homepage.handbags.update') }}" class="admin-form-stack homepage-explore-collections-editor homepage-handbags-editor" data-collection-picker-endpoint="{{ route('admin.homepage.collection-picker') }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="lock_version" value="{{ old('lock_version', $homepage->lock_version) }}">
        <section class="admin-panel">
            <div class="admin-section-heading"><p>Section status</p><h2>{{ $section['managed'] ? ($section['eligible'] && $section['ready_count'] > 0 ? 'Using managed content from Administration' : 'Needs attention') : 'Using storefront default' }}</h2></div>
            <input type="hidden" name="handbags_managed" value="0">
            <label class="admin-choice-row"><input type="checkbox" name="handbags_managed" value="1" @checked(old('handbags_managed', $homepage->handbags_managed))> Use managed content</label>
            <p class="admin-field-help">Status reflects saved settings. Enable and save to publish this feature. When off, the original storefront section remains visible.</p>
            @if($section['managed'])
                <p class="admin-field-help">{{ $section['ready_count'] }} storefront-ready Products · {{ $section['attention_count'] }} need attention. Up to six are shown in Collection order.</p>
                @if(!$section['eligible'])<p class="admin-field-error">The featured Collection or hero images are unavailable. Update the selection to restore this feature.</p>@endif
            @endif
        </section>
        <section class="admin-panel">
            <div class="admin-section-heading"><p>Section content</p><h2>For her</h2></div>
            <div class="admin-form-grid">
                @foreach(['eyebrow' => 'Eyebrow', 'heading' => 'Heading', 'cta_label' => 'View all label'] as $key => $label)
                    @php
                        $field = 'handbags_'.$key;
                    @endphp
                    <x-admin.field :label="$label" :for="$field" :error="$errors->first($field)"><input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $homepage->getAttribute($field)) }}" required></x-admin.field>
                @endforeach
            </div>
        </section>
        <section class="admin-panel">
            <div class="admin-section-heading"><p>Featured Collection</p><h2>Six curated Products</h2></div>
            <p class="admin-field-help">Both actions open this Collection. Product names, images, prices and order come from the Collection's Products. Unavailable Products are omitted.</p>
            @php
                $position = 1;
                $selected = $selectedCollection;
                $selectedImage = $selected['image']['url'] ?? null;
                $field = 'handbags_collection_id';
                $fieldError = $errors->first($field);
            @endphp
            @include('admin.homepage.collection-picker-slot')
            @if($selected)
                <div class="admin-page-actions"><a class="admin-secondary-button" href="{{ route('admin.collections.edit', $selected['id']) }}">Manage Collection and Product order</a></div>
            @endif
        </section>
        <section class="admin-panel">
            <div class="admin-section-heading"><p>Editorial hero</p><h2>Crafted for her</h2></div>
            <div class="admin-form-grid">
                @foreach(['hero_eyebrow' => 'Hero eyebrow', 'hero_heading' => 'Hero heading', 'hero_copy' => 'Supporting copy', 'hero_cta_label' => 'Hero action label'] as $key => $label)
                    @php
                        $field = 'handbags_'.$key;
                    @endphp
                    <x-admin.field :label="$label" :for="$field" :error="$errors->first($field)">
                        @if($key === 'hero_copy')
                            <textarea id="{{ $field }}" name="{{ $field }}" maxlength="500" required>{{ old($field, $homepage->getAttribute($field)) }}</textarea>
                        @else
                            <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $homepage->getAttribute($field)) }}" required>
                        @endif
                    </x-admin.field>
                @endforeach
            </div>
            <p class="admin-field-help">Choose two editorial images for the five-second slideshow. Product card images continue to come from each Product.</p>
            <div class="admin-form-grid">
                @foreach([1, 2] as $position)
                    <div class="admin-form-section">
                        <h3>Image {{ $position }}</h3>
                        <input type="hidden" name="handbags_image_{{ $position }}" value="">
                        <x-admin.media-picker :id="'handbags-image-'.$position" :name="'handbags_image_'.$position" :selected="$selectedMedia[$position]" :error="$errors->first('handbags_image_'.$position)" />
                        <x-admin.field label="Alt text override" :for="'handbags_alt_'.$position" :error="$errors->first('handbags_alt_'.$position)" help="Optional when the selected image has default alt text in Media Library. Otherwise, enter alt text here or add the default in Media Library."><input id="handbags_alt_{{ $position }}" name="handbags_alt_{{ $position }}" value="{{ old('handbags_alt_'.$position, $alts[$position]) }}" maxlength="500" @if($errors->has('handbags_alt_'.$position)) aria-invalid="true" @endif></x-admin.field>
                    </div>
                @endforeach
            </div>
        </section>
        <x-admin.form-actions>
            <x-slot:secondary><a class="admin-secondary-button" href="{{ route('admin.homepage.edit') }}">Back to Homepage</a></x-slot:secondary>
            <x-slot:primary><button class="admin-primary-button">Save changes</button></x-slot:primary>
        </x-admin.form-actions>
        @include('admin.homepage.collection-picker-dialog')
    </form>
    @include('admin.homepage.collection-picker-script')
</x-admin.layout>
