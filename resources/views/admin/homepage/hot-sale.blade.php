@php
    $breadcrumbs = ['Homepage' => route('admin.homepage.edit'), "William's Hot Sale" => null];
@endphp

<x-admin.layout title="Homepage / William's Hot Sale" description="Manage the three editorial feature tiles already shown on the Homepage." eyebrow="Website" :breadcrumbs="$breadcrumbs">
    <x-admin.flash :errors="$errors" />
    <x-admin.homepage-section-visibility section-key="hot-sale" />
    <form method="POST" action="{{ route('admin.homepage.hot-sale.update') }}" class="homepage-hot-sale-editor">
        @csrf
        @method('PUT')
        <input type="hidden" name="lock_version" value="{{ old('lock_version', $homepage->lock_version) }}">

        <section class="admin-panel">
            <div class="admin-section-heading"><p>William's Hot Sale</p><h2>Section heading</h2></div>
            <div class="admin-form-grid">
                <x-admin.field label="Eyebrow" for="hot-sale-eyebrow" :error="$errors->first('hot_sale_eyebrow')"><input id="hot-sale-eyebrow" name="hot_sale_eyebrow" value="{{ old('hot_sale_eyebrow', $homepage->hot_sale_eyebrow) }}" maxlength="120" required></x-admin.field>
                <x-admin.field label="Heading" for="hot-sale-heading" :error="$errors->first('hot_sale_heading')"><input id="hot-sale-heading" name="hot_sale_heading" value="{{ old('hot_sale_heading', $homepage->hot_sale_heading) }}" maxlength="160" required></x-admin.field>
            </div>
        </section>

        @foreach ([1, 2, 3] as $position)
            @php
                $titleField = "hot_sale_tile_{$position}_title";
                $copyField = "hot_sale_tile_{$position}_copy";
                $ctaField = "hot_sale_tile_{$position}_cta_label";
                $destinationField = "hot_sale_tile_{$position}_destination";
                $mediaField = "hot_sale_tile_{$position}_media_id";
                $altField = "hot_sale_tile_{$position}_alt_override";
            @endphp
            <section class="admin-panel hot-sale-admin-tile">
                <div class="admin-section-heading"><p>Feature {{ str_pad((string) $position, 2, '0', STR_PAD_LEFT) }}</p><h2>Editorial tile</h2></div>
                <div class="admin-form-grid">
                    <x-admin.field label="Title" for="hot-sale-{{ $position }}-title" :error="$errors->first($titleField)"><input id="hot-sale-{{ $position }}-title" name="{{ $titleField }}" value="{{ old($titleField, data_get($homepage, $titleField)) }}" maxlength="160" required></x-admin.field>
                    <x-admin.field label="Action label" for="hot-sale-{{ $position }}-cta" :error="$errors->first($ctaField)"><input id="hot-sale-{{ $position }}-cta" name="{{ $ctaField }}" value="{{ old($ctaField, data_get($homepage, $ctaField)) }}" maxlength="80" required></x-admin.field>
                    <x-admin.field label="Short copy" for="hot-sale-{{ $position }}-copy" :error="$errors->first($copyField)"><textarea id="hot-sale-{{ $position }}-copy" name="{{ $copyField }}" maxlength="320" required>{{ old($copyField, data_get($homepage, $copyField)) }}</textarea></x-admin.field>
                    <x-admin.field label="Destination" for="hot-sale-{{ $position }}-destination" :error="$errors->first($destinationField)"><select id="hot-sale-{{ $position }}-destination" name="{{ $destinationField }}">@foreach($destinations as $value => $label)<option value="{{ $value }}" @selected(old($destinationField, data_get($homepage, $destinationField)) === $value)>{{ $label }}</option>@endforeach</select></x-admin.field>
                </div>
                <div class="admin-section-heading"><p>Tile image</p><h3>Ready Media</h3></div>
                <p class="admin-field-help">Choose a confirmed ready image or video with meaningful alternative text. Removing this usage preserves the Media Asset.</p>
                <x-admin.media-picker id="hot-sale-{{ $position }}-media" :name="$mediaField" :selected="$selectedMedia[$position]" button-label="Choose media" change-label="Change media" asset-label="media" :endpoint="route('admin.homepage.hot-sale.media-picker')" :error="$errors->first($mediaField)" />
                <x-admin.field label="Alt text override (optional)" for="hot-sale-{{ $position }}-alt" help="Leave blank to use the Media Library alt text." :error="$errors->first($altField)">
                    <input id="hot-sale-{{ $position }}-alt" name="{{ $altField }}" value="{{ $mediaAltOverrides[$position] }}" maxlength="320">
                </x-admin.field>
                <a class="admin-secondary-button" href="{{ route('admin.media.index') }}">Open Media Library</a>
            </section>
        @endforeach

        <x-admin.form-actions>
            <x-slot:secondary><a class="admin-secondary-button" href="{{ route('admin.homepage.edit') }}">Back to Homepage</a></x-slot:secondary>
            <x-slot:primary><button class="admin-primary-button">Save changes</button></x-slot:primary>
        </x-admin.form-actions>
    </form>
</x-admin.layout>
