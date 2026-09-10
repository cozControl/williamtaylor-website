<x-admin.layout title="Limited Edition" eyebrow="Homepage" heading="Limited Edition" description="Choose up to three published Limited Edition Campaigns for the supplied Homepage composition.">
    <x-admin.flash :errors="$errors" />
    <x-admin.homepage-section-visibility section-key="limited-edition" />
    <form method="POST" action="{{ route('admin.homepage.limited-edition.update') }}" class="admin-form-stack">
        @csrf
        @method('PUT')
        <input type="hidden" name="lock_version" value="{{ old('lock_version', $homepage->lock_version) }}">
        <section class="admin-form-section">
            <label class="admin-choice-row"><input type="checkbox" name="limited_edition_managed" value="1" @checked(old('limited_edition_managed', $homepage->limited_edition_managed))> Use managed content</label>
            <p>Enable to use the text below. Campaigns always come from the database. Saved positions take priority; with no selections, up to three eligible Limited Edition Campaigns appear in schedule order.</p>
            <div class="admin-form-grid">
                <x-admin.field label="Eyebrow" for="limited-edition-eyebrow" :error="$errors->first('limited_edition_eyebrow')">
                    <input id="limited-edition-eyebrow" name="limited_edition_eyebrow" value="{{ old('limited_edition_eyebrow', $homepage->limited_edition_eyebrow) }}" maxlength="120" required>
                </x-admin.field>
                <x-admin.field label="Heading" for="limited-edition-heading" :error="$errors->first('limited_edition_heading')">
                    <input id="limited-edition-heading" name="limited_edition_heading" value="{{ old('limited_edition_heading', $homepage->limited_edition_heading) }}" maxlength="160" required>
                </x-admin.field>
                <x-admin.field label="View All label" for="limited-edition-cta" :error="$errors->first('limited_edition_cta_label')">
                    <input id="limited-edition-cta" name="limited_edition_cta_label" value="{{ old('limited_edition_cta_label', $homepage->limited_edition_cta_label) }}" maxlength="80" required>
                </x-admin.field>
            </div>
        </section>
        <section class="admin-form-section">
            <div class="admin-section-heading"><p>Featured Campaigns</p><h2>Three supplied positions</h2></div>
            <p class="admin-field-help">Position order is preserved. Ended, unpublished, or Product-incomplete Campaigns are safely omitted.</p>
            <div class="admin-form-grid">
                @foreach(range(1, \App\Domain\Homepage\Support\HomepageLimitedEditionPresenter::CAPACITY) as $position)
                    <x-admin.field :label="'Position '.$position" :for="'limited-edition-campaign-'.$position" :error="$errors->first('limited_edition_campaign_'.$position.'_id')">
                        <select id="limited-edition-campaign-{{ $position }}" name="limited_edition_campaign_{{ $position }}_id">
                            <option value="">No Campaign</option>
                            @foreach($campaigns as $campaign)
                                <option value="{{ $campaign->id }}" @selected((string) old("limited_edition_campaign_{$position}_id", $homepage->{"limited_edition_campaign_{$position}_id"}) === $campaign->id)>{{ $campaign->currentDraftRevision?->headline ?? 'Untitled Campaign' }} — {{ $campaign->lifecycle_status === 'active' ? 'Published' : 'Draft' }}</option>
                            @endforeach
                        </select>
                    </x-admin.field>
                @endforeach
            </div>
        </section>
        <x-admin.form-actions>
            <x-slot:secondary><a class="admin-secondary-button" href="{{ route('admin.homepage.edit') }}">Back</a></x-slot:secondary>
            <x-slot:primary><button class="admin-primary-button">Save Limited Edition</button></x-slot:primary>
        </x-admin.form-actions>
    </form>
</x-admin.layout>
