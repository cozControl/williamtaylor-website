<x-admin.layout title="Homepage / New Arrivals" description="Choose the Collection that supplies the existing New Arrivals Product cards." eyebrow="Website" :breadcrumbs="['Homepage' => route('admin.homepage.edit'), 'New Arrivals' => null]">
    <x-admin.flash :errors="$errors" />

    <form method="POST" action="{{ route('admin.homepage.new-arrivals.update') }}" class="homepage-new-arrivals-editor" data-collection-picker-endpoint="{{ route('admin.homepage.collection-picker') }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="lock_version" value="{{ old('lock_version', $homepage->lock_version) }}">

        <section class="admin-panel">
            <div class="admin-section-heading"><p>New Arrivals</p><h2>Visible content</h2></div>
            <div class="admin-form-grid">
                <x-admin.field label="Eyebrow" for="new-arrivals-eyebrow" :error="$errors->first('new_arrivals_eyebrow')"><input id="new-arrivals-eyebrow" name="new_arrivals_eyebrow" value="{{ old('new_arrivals_eyebrow', $homepage->new_arrivals_eyebrow) }}" maxlength="120" required></x-admin.field>
                <x-admin.field label="Heading" for="new-arrivals-heading" :error="$errors->first('new_arrivals_heading')"><input id="new-arrivals-heading" name="new_arrivals_heading" value="{{ old('new_arrivals_heading', $homepage->new_arrivals_heading) }}" maxlength="160" required></x-admin.field>
                <x-admin.field label="CTA label" for="new-arrivals-cta" :error="$errors->first('new_arrivals_cta_label')"><input id="new-arrivals-cta" name="new_arrivals_cta_label" value="{{ old('new_arrivals_cta_label', $homepage->new_arrivals_cta_label) }}" maxlength="80" required><small>The destination is always the selected Collection.</small></x-admin.field>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-section-heading"><p>Product source</p><h2>Collection</h2></div>
            <input type="hidden" name="new_arrivals_collection_id" id="new-arrivals-collection-id" value="{{ old('new_arrivals_collection_id', $homepage->new_arrivals_collection_id) }}">
            <div class="homepage-collection-selection" data-collection-selection>
                @if($section['managed'])
                    <strong>{{ $section['collection_name'] }}</strong>
                    <span>{{ $section['total_count'] }} Products · {{ $section['ready_count'] }} ready for storefront · {{ $section['attention_count'] }} need attention</span>
                    @if($section['ready_count'] === 0)<small class="admin-field-error">This section has no Products ready to display.</small>@endif
                @else
                    <strong>No Collection selected</strong>
                    <span>The original static cards remain visible until a Collection is saved.</span>
                @endif
            </div>
            @error('new_arrivals_collection_id')<p class="admin-field-error">{{ $message }}</p>@enderror
            <button class="admin-secondary-button" type="button" data-open-collection-picker>Change Collection</button>
        </section>

        <x-admin.form-actions>
            <x-slot:secondary><a class="admin-secondary-button" href="{{ route('admin.homepage.edit') }}">Back to Homepage</a></x-slot:secondary>
            <x-slot:primary><button class="admin-primary-button">Save changes</button></x-slot:primary>
        </x-admin.form-actions>

        <dialog class="homepage-collection-picker" data-collection-picker>
            <div class="admin-picker-dialog-header"><div><p>Homepage</p><h2>Choose Collection</h2></div><button type="button" aria-label="Close" data-close-collection-picker>×</button></div>
            <label class="admin-field"><span class="admin-field-label">Search Collections</span><input type="search" data-collection-search placeholder="Collection name"></label>
            <div class="homepage-collection-results" data-collection-results><p>Search or browse eligible Collections.</p></div>
        </dialog>
    </form>

    <script>
    (() => {
     const form = document.querySelector('.homepage-new-arrivals-editor');
     if (!form) return;
     const dialog = form.querySelector('[data-collection-picker]');
     const results = form.querySelector('[data-collection-results]');
     const search = form.querySelector('[data-collection-search]');
     const selected = form.querySelector('[data-collection-selection]');
     const input = form.querySelector('#new-arrivals-collection-id');
     let timer;
     const escape = value => String(value).replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character]));
     const load = async () => {
      results.innerHTML = '<p>Loading Collections…</p>';
      const url = new URL(form.dataset.collectionPickerEndpoint, window.location.origin);
      url.searchParams.set('search', search.value.trim());
      const response = await fetch(url, {headers: {'Accept': 'application/json'}});
      const payload = await response.json();
      results.innerHTML = payload.data.length ? payload.data.map(collection => `<button type="button" class="homepage-collection-result" data-id="${escape(collection.id)}" data-name="${escape(collection.name)}" data-total="${collection.product_count}" data-ready="${collection.ready_count}">${collection.image ? `<img src="${escape(collection.image)}" alt="">` : '<span class="admin-image-empty">No image</span>'}<strong>${escape(collection.name)}</strong><span>${collection.product_count} Products · ${collection.ready_count} ready</span><small>${escape(collection.status)}</small></button>`).join('') : '<p class="admin-picker-empty">No eligible Collections found.</p>';
     };
     form.querySelector('[data-open-collection-picker]').addEventListener('click', () => { dialog.showModal(); load(); });
     form.querySelector('[data-close-collection-picker]').addEventListener('click', () => dialog.close());
     search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 250); });
     results.addEventListener('click', event => {
      const choice = event.target.closest('[data-id]');
      if (!choice) return;
      input.value = choice.dataset.id;
      const attention = Number(choice.dataset.total) - Number(choice.dataset.ready);
      selected.innerHTML = `<strong>${escape(choice.dataset.name)}</strong><span>${choice.dataset.total} Products · ${choice.dataset.ready} ready for storefront · ${attention} need attention</span>${Number(choice.dataset.ready) === 0 ? '<small class="admin-field-error">This Collection has no Products ready for the storefront.</small>' : ''}`;
      dialog.close();
     });
    })();
    </script>
</x-admin.layout>
