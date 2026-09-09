<x-admin.layout title="Complimentary Delivery" eyebrow="Homepage" description="Manage the delivery information shown below The Summer Edit." :breadcrumbs="['Homepage' => route('admin.homepage.edit'), 'Complimentary Delivery' => null]">
    <x-admin.flash :errors="$errors" />
    <div class="admin-page-actions"><a class="admin-secondary-button" href="{{ route('home') }}" target="_blank" rel="noopener">View homepage</a></div>
    <form method="POST" action="{{ route('admin.homepage.delivery.update') }}" class="admin-form-stack homepage-delivery-editor">
        @csrf
        @method('PUT')
        <input type="hidden" name="lock_version" value="{{ old('lock_version', $homepage->lock_version) }}">
        <section class="admin-panel">
            <div class="admin-section-heading"><p>Section status</p><h2>{{ $section['managed'] ? ($section['eligible'] ? 'Using managed content from Administration' : 'Needs attention') : 'Using storefront default' }}</h2></div>
            <label class="admin-choice-row"><input type="checkbox" name="delivery_managed" value="1" @checked(old('delivery_managed', $homepage->delivery_managed))> Use managed content</label>
            <p class="admin-field-help">Status reflects saved settings. Enable and save to publish this strip. When off, the original storefront content remains.</p>
        </section>
        <section class="admin-panel">
            <div class="admin-section-heading"><p>Delivery information</p><h2>Homepage message</h2></div>
            <p class="admin-field-help">Use approved delivery wording. This message does not calculate delivery charges or change checkout rules.</p>
            <div class="admin-form-grid">
                @foreach(['eyebrow' => ['Eyebrow', 120], 'heading' => ['Heading', 160], 'cta_label' => ['Action label', 80]] as $key => $fieldConfig)
                    @php
                        $field = 'delivery_'.$key;
                    @endphp
                    <x-admin.field :label="$fieldConfig[0]" :for="$field" :error="$errors->first($field)">
                        <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $homepage->getAttribute($field)) }}" maxlength="{{ $fieldConfig[1] }}" required>
                    </x-admin.field>
                @endforeach
            </div>
        </section>
        <section class="admin-panel">
            <div class="admin-section-heading"><p>Action destination</p><h2>Using Site Settings</h2></div>
            <p class="admin-field-help">Contact details come from published Site Settings. Update them there to keep the website consistent.</p>
            <x-admin.field label="Contact method" for="delivery_destination" :error="$errors->first('delivery_destination')">
                <select id="delivery_destination" name="delivery_destination">
                    @foreach(['contact_email' => 'Email', 'contact_whatsapp' => 'WhatsApp'] as $key => $label)
                        <option value="{{ $key }}" @selected(old('delivery_destination', $homepage->delivery_destination) === $key)>{{ $label }}{{ $destinations[$key] === null ? ' — unavailable in published Site Settings' : '' }}</option>
                    @endforeach
                </select>
            </x-admin.field>
            @foreach($destinations as $key => $url)
                <p class="admin-field-help">{{ $key === 'contact_email' ? 'Email' : 'WhatsApp' }}: {{ $url ?? 'Not currently published' }}</p>
            @endforeach
            <a class="admin-secondary-button" href="{{ route('admin.settings.index') }}">Manage Site Settings</a>
        </section>
        <x-admin.form-actions>
            <x-slot:secondary><a class="admin-secondary-button" href="{{ route('admin.homepage.edit') }}">Back to Homepage</a></x-slot:secondary>
            <x-slot:primary><button class="admin-primary-button">Save changes</button></x-slot:primary>
        </x-admin.form-actions>
    </form>
</x-admin.layout>
