<x-admin.layout title="Explore the Collection" eyebrow="Homepage" description="Choose up to three canonical Collections for the existing Homepage composition." :breadcrumbs="['Homepage' => route('admin.homepage.edit'), 'Explore the Collection' => null]">
    <x-admin.flash :errors="$errors" />

    <section class="admin-form-section" aria-label="Current storefront state">
        <p class="admin-status-label">Current storefront state</p>
        @if($homepage->explore_collections_managed)
            <strong>USING MANAGED COLLECTIONS</strong>
            <p class="admin-field-help">The saved Collections are published in position order. Unavailable Collections are omitted.</p>
        @else
            <strong>USING STOREFRONT DEFAULT</strong>
            <p class="admin-field-help">Selected Collections below are not currently published because managed content is off.</p>
        @endif
        <p class="admin-field-help">This status reflects the saved Homepage. Changes take effect after you save.</p>
    </section>

    <form method="POST" action="{{ route('admin.homepage.explore-collections.update') }}" class="homepage-explore-collections-editor admin-form-stack" data-collection-picker-endpoint="{{ route('admin.homepage.collection-picker') }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="lock_version" value="{{ old('lock_version', $homepage->lock_version) }}">

        <section class="admin-form-section">
            <label class="admin-choice-row">
                <input type="checkbox" name="explore_collections_managed" value="1" @checked(old('explore_collections_managed', $homepage->explore_collections_managed))>
                Use managed content
            </label>
            <p class="admin-field-help">Enable Use managed content and save to publish the selected Collections and text. Saving with this off retains the original storefront section, even when Collections are selected.</p>
            <div class="admin-form-grid">
                <x-admin.field label="Eyebrow" for="explore-collections-eyebrow" :error="$errors->first('explore_collections_eyebrow')">
                    <input id="explore-collections-eyebrow" name="explore_collections_eyebrow" value="{{ old('explore_collections_eyebrow', $homepage->explore_collections_eyebrow) }}" maxlength="120" required>
                </x-admin.field>
                <x-admin.field label="Heading" for="explore-collections-heading" :error="$errors->first('explore_collections_heading')">
                    <input id="explore-collections-heading" name="explore_collections_heading" value="{{ old('explore_collections_heading', $homepage->explore_collections_heading) }}" maxlength="160" required>
                </x-admin.field>
            </div>
        </section>

        <section class="admin-form-section">
            <div class="admin-section-heading">
                <p>Collections</p>
                <h2>Three supplied positions</h2>
            </div>
            <p class="admin-field-help">Position order is preserved on the Homepage. Hidden, archived, or image-incomplete Collections are safely omitted from the storefront.</p>

            <div class="homepage-explore-collection-slots">
                @foreach(range(1, \App\Domain\Homepage\Support\HomepageExploreCollectionsPresenter::CAPACITY) as $position)
                    @php
                        $selected = $selectedCollections[$position];
                        $selectedImage = is_array($selected['image'] ?? null) ? ($selected['image']['url'] ?? null) : ($selected['image'] ?? null);
                        $field = "explore_collection_{$position}_id";
                        $fieldError = $errors->first($field);
                    @endphp
                    @include('admin.homepage.collection-picker-slot')
                @endforeach
            </div>
        </section>

        <x-admin.form-actions>
            <x-slot:secondary><a class="admin-secondary-button" href="{{ route('admin.homepage.edit') }}">Back</a></x-slot:secondary>
            <x-slot:primary><button class="admin-primary-button">Save changes</button></x-slot:primary>
        </x-admin.form-actions>

        @include('admin.homepage.collection-picker-dialog')
    </form>

    @include('admin.homepage.collection-picker-script')
</x-admin.layout>
