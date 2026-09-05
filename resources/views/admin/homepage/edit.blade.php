<x-admin.layout title="Homepage" description="Manage the main visual and message shown at the top of the storefront." eyebrow="Website" :breadcrumbs="['Homepage' => null]">
    <x-admin.flash :errors="$errors" />

    <div class="admin-page-actions">
        <a class="admin-secondary-button" href="{{ route('home') }}" target="_blank" rel="noopener">View homepage</a>
    </div>

    <form method="POST" action="{{ route('admin.homepage.update') }}" class="homepage-hero-editor">
        @csrf
        @method('PUT')
        <input type="hidden" name="lock_version" value="{{ old('lock_version', $hero->lock_version) }}">

        <section class="admin-panel">
            <div class="admin-section-heading"><p>Homepage Hero</p><h2>Content</h2></div>
            <div class="admin-form-grid">
                <x-admin.field label="Eyebrow" for="hero-eyebrow" :error="$errors->first('eyebrow')"><input id="hero-eyebrow" name="eyebrow" value="{{ old('eyebrow', $hero->eyebrow) }}" maxlength="120" required></x-admin.field>
                <x-admin.field label="Title" for="hero-title" :error="$errors->first('title')"><input id="hero-title" name="title" value="{{ old('title', $hero->title) }}" maxlength="160" required></x-admin.field>
                <x-admin.field label="Subtitle" for="hero-subtitle" :error="$errors->first('subtitle')"><input id="hero-subtitle" name="subtitle" value="{{ old('subtitle', $hero->subtitle) }}" maxlength="240" required></x-admin.field>
                <label class="admin-choice-row admin-form-wide"><input type="checkbox" name="scroll_indicator_enabled" value="1" @checked(old('scroll_indicator_enabled', $hero->scroll_indicator_enabled))> Show the existing Scroll cue</label>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-section-heading"><p>Primary action</p><h2>Shop New Arrivals</h2></div>
            <div class="admin-form-grid">
                <x-admin.field label="Label" for="hero-primary-label" :error="$errors->first('primary_cta_label')"><input id="hero-primary-label" name="primary_cta_label" value="{{ old('primary_cta_label', $hero->primary_cta_label) }}" maxlength="80" required></x-admin.field>
                <x-admin.field label="Destination" for="hero-primary-destination" :error="$errors->first('primary_cta_destination')"><select id="hero-primary-destination" name="primary_cta_destination">@foreach($destinations as $value => $label)<option value="{{ $value }}" @selected(old('primary_cta_destination', $hero->primary_cta_destination) === $value)>{{ $label }}</option>@endforeach</select></x-admin.field>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-section-heading"><p>Secondary action</p><h2>Explore Collections</h2></div>
            <div class="admin-form-grid">
                <x-admin.field label="Label" for="hero-secondary-label" :error="$errors->first('secondary_cta_label')"><input id="hero-secondary-label" name="secondary_cta_label" value="{{ old('secondary_cta_label', $hero->secondary_cta_label) }}" maxlength="80" required></x-admin.field>
                <x-admin.field label="Destination" for="hero-secondary-destination" :error="$errors->first('secondary_cta_destination')"><select id="hero-secondary-destination" name="secondary_cta_destination">@foreach($destinations as $value => $label)<option value="{{ $value }}" @selected(old('secondary_cta_destination', $hero->secondary_cta_destination) === $value)>{{ $label }}</option>@endforeach</select></x-admin.field>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-section-heading"><p>Hero media</p><h2>Background image</h2></div>
            <p class="admin-field-help">The current code-owned image remains as a safe fallback when no Media Asset is selected.</p>
            <x-admin.media-picker id="homepage-hero-media" name="background_media_id" :selected="$selectedMedia" button-label="Choose media" change-label="Change media" :error="$errors->first('background_media_id')" />
        </section>

        <x-admin.form-actions>
            <x-slot:secondary><a class="admin-secondary-button" href="{{ route('admin.dashboard') }}">Back</a></x-slot:secondary>
            <x-slot:primary><button class="admin-primary-button">Save changes</button></x-slot:primary>
        </x-admin.form-actions>
    </form>
</x-admin.layout>
