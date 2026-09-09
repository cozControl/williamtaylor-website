<x-admin.layout title="The Summer Edit" eyebrow="Homepage" description="Manage this seasonal editorial feature." :breadcrumbs="['Homepage' => route('admin.homepage.edit'), 'The Summer Edit' => null]">
    <x-admin.flash :errors="$errors" />
    <div class="admin-page-actions"><a class="admin-secondary-button" href="{{ route('home') }}" target="_blank" rel="noopener">View homepage</a></div>
    <form method="POST" action="{{ route('admin.homepage.summer-edit.update') }}" class="admin-form-stack homepage-summer-edit-editor">
        @csrf
        @method('PUT')
        <input type="hidden" name="lock_version" value="{{ old('lock_version', $homepage->lock_version) }}">
        <section class="admin-panel">
            <div class="admin-section-heading"><p>Section status</p><h2>{{ $section['managed'] ? ($section['eligible'] ? 'Using managed content from Administration' : 'Needs attention') : 'Using storefront default' }}</h2></div>
            <label class="admin-choice-row"><input type="checkbox" name="summer_edit_managed" value="1" @checked(old('summer_edit_managed', $homepage->summer_edit_managed))> Use managed content</label>
            <p class="admin-field-help">The status reflects saved settings. Enable and save to publish this feature. When off, selected values are not published.</p>
        </section>
        <section class="admin-panel">
            <div class="admin-section-heading"><p>Section content</p><h2>Seasonal editorial feature</h2></div>
            <div class="admin-form-grid">
                @foreach(['eyebrow' => 'Eyebrow', 'heading' => 'Heading', 'copy_prefix' => 'Promotional opening', 'highlight' => 'Highlighted phrase', 'copy' => 'Promotional copy', 'cta_label' => 'Action label'] as $key => $label)
                    @php
                        $field = 'summer_edit_'.$key;
                    @endphp
                    <x-admin.field :label="$label" :for="$field" :error="$errors->first($field)">
                        @if($key === 'copy')
                            <textarea id="{{ $field }}" name="{{ $field }}" maxlength="500" required>{{ old($field, $homepage->getAttribute($field)) }}</textarea>
                        @else
                            <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $homepage->getAttribute($field)) }}" required>
                        @endif
                    </x-admin.field>
                @endforeach
            </div>
            <p class="admin-field-help">Promotional wording is editorial content. Saving it does not change Product prices. The William Taylor background pattern is retained.</p>
        </section>
        <section class="admin-panel">
            <div class="admin-section-heading"><p>Destination</p><h2>Featured Collection</h2></div>
            <x-admin.field label="Collection" for="summer_edit_collection_id" :error="$errors->first('summer_edit_collection_id')" help="Choose the Collection containing this edit. Its current public page will open from the button.">
                <select id="summer_edit_collection_id" name="summer_edit_collection_id">
                    <option value="">Choose a Collection</option>
                    @php
                        $selectedId = old('summer_edit_collection_id', $homepage->summer_edit_collection_id);
                    @endphp
                    @if(filled($selectedId) && !$collections->contains('id', $selectedId))
                        <option value="{{ $selectedId }}" selected>Unavailable Collection — choose another</option>
                    @endif
                    @foreach($collections as $collection)
                        <option value="{{ $collection['id'] }}" @selected(old('summer_edit_collection_id', $homepage->summer_edit_collection_id) === $collection['id'])>{{ $collection['title'] }} — /collections/{{ $collection['slug'] }}</option>
                    @endforeach
                </select>
            </x-admin.field>
            @if($section['managed'] && !$section['eligible'])
                <p class="admin-field-error">Choose an available Collection before this feature can appear on the Homepage.</p>
            @endif
        </section>
        <x-admin.form-actions>
            <x-slot:secondary><a class="admin-secondary-button" href="{{ route('admin.homepage.edit') }}">Back to Homepage</a></x-slot:secondary>
            <x-slot:primary><button class="admin-primary-button">Save changes</button></x-slot:primary>
        </x-admin.form-actions>
    </form>
</x-admin.layout>
