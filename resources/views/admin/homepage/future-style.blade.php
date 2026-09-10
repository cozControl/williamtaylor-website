<x-admin.layout title="The Future of Style" eyebrow="Homepage" heading="The Future of Style" description="Choose up to two published Pre-Order Campaigns for the supplied Homepage composition.">
    <x-admin.homepage-section-visibility section-key="future-style" />
    @if($errors->any())
        <div class="admin-alert admin-alert-error" role="alert">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif
    <form method="POST" action="{{ route('admin.homepage.future-style.update') }}" class="admin-form-stack">@csrf @method('PUT')
        <input type="hidden" name="lock_version" value="{{ $homepage->lock_version }}">
        <section class="admin-form-section"><label><input type="checkbox" name="future_style_managed" value="1" @checked(old('future_style_managed', $homepage->future_style_managed))> Use managed content</label><p>Enable to use the text below. Campaigns always come from the database. Saved positions take priority; with no selections, up to two eligible Pre-Order Campaigns appear in schedule order. Choose different campaigns; unavailable selections are omitted.</p><div class="admin-form-grid">
            <label>Eyebrow<input name="future_style_eyebrow" value="{{ old('future_style_eyebrow', $homepage->future_style_eyebrow) }}" required></label>
            <label>Heading<input name="future_style_heading" value="{{ old('future_style_heading', $homepage->future_style_heading) }}" required></label>
            <label class="admin-form-span">Introduction<textarea name="future_style_intro" required>{{ old('future_style_intro', $homepage->future_style_intro) }}</textarea></label>
            <label>View All label<input name="future_style_cta_label" value="{{ old('future_style_cta_label', $homepage->future_style_cta_label) }}" required></label>
        </div></section>
        <section class="admin-form-section"><p class="admin-kicker">Featured Campaigns</p><div class="admin-form-grid">@foreach([1,2] as $position)<label>Position {{ $position }}<select name="future_style_campaign_{{ $position }}_id"><option value="">No Campaign</option>@foreach($campaigns as $campaign)<option value="{{ $campaign->id }}" @selected(old("future_style_campaign_{$position}_id", $homepage->{"future_style_campaign_{$position}_id"}) === $campaign->id)>{{ $campaign->currentDraftRevision?->headline }} — {{ $campaign->lifecycle_status }}</option>@endforeach</select></label>@endforeach</div></section>
        <div class="admin-form-actions"><a class="admin-button admin-button-secondary" href="{{ route('admin.homepage.edit') }}">Back</a><button class="admin-button admin-button-primary">Save The Future of Style</button></div>
    </form>
</x-admin.layout>
