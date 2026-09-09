<x-admin.layout title="Client Stories" eyebrow="Homepage" description="Manage the three client experiences featured on the Homepage." :breadcrumbs="['Homepage' => route('admin.homepage.edit'), 'Client Stories' => null]">
    <x-admin.flash :errors="$errors" />
    <div class="admin-page-actions"><a class="admin-secondary-button" href="{{ route('home') }}" target="_blank" rel="noopener">View homepage</a></div>
    <form method="POST" action="{{ route('admin.homepage.client-stories.update') }}" class="admin-form-stack homepage-client-stories-editor">
        @csrf
        @method('PUT')
        <input type="hidden" name="lock_version" value="{{ old('lock_version', $homepage->lock_version) }}">
        <section class="admin-panel">
            <div class="admin-section-heading"><p>Section status</p><h2>{{ $section['managed'] ? ($section['attention_count'] ? 'Needs attention' : 'Using managed content from Administration') : 'Using storefront default' }}</h2></div>
            <input type="hidden" name="client_stories_managed" value="0">
            <label class="admin-choice-row"><input type="checkbox" name="client_stories_managed" value="1" @checked(old('client_stories_managed', $homepage->client_stories_managed))> Use managed content</label>
            <p class="admin-field-help">Status reflects saved settings. Enable and save to publish these stories. When off, the original storefront section remains visible.</p>
            @if($section['managed'])<p class="admin-field-help">{{ count($section['stories']) }} visible stories ready for the storefront. With no visible stories, the section is hidden.</p>@endif
        </section>
        <section class="admin-panel">
            <div class="admin-section-heading"><p>Section content</p><h2>What they say</h2></div>
            <div class="admin-form-grid">
                @foreach(['eyebrow' => 'Eyebrow', 'heading' => 'Heading'] as $key => $label)
                    @php
                        $field = 'client_stories_'.$key;
                    @endphp
                    <x-admin.field :label="$label" :for="$field" :error="$errors->first($field)"><input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $homepage->getAttribute($field)) }}" required></x-admin.field>
                @endforeach
            </div>
        </section>
        @foreach(range(1, \App\Domain\Homepage\Models\HomepageClientStory::CAPACITY) as $position)
            @php
                $story = $stories->get($position);
                $status = $section['positions'][$position] ?? null;
            @endphp
            <section class="admin-panel" data-client-story-position="{{ $position }}">
                <div class="admin-section-heading"><p>Position {{ $position }}</p><h2>{{ $story?->display_name ?: 'Client story' }}</h2></div>
                <p class="admin-status-label">{{ !$status || !$status['visible'] ? 'Hidden' : ($status['ready'] ? 'Ready' : 'Needs attention') }}</p>
                @if($status && $status['visible'] && $status['reason'])<p class="admin-field-error">{{ $status['reason'] }}</p>@endif
                <input type="hidden" name="stories[{{ $position }}][is_visible]" value="0">
                <label class="admin-choice-row"><input type="checkbox" name="stories[{{ $position }}][is_visible]" value="1" @checked(old('stories.'.$position.'.is_visible', $story?->is_visible ?? false))> Show this story</label>
                <p class="admin-field-help">Publish an approved client quote. Turn this off to remove it from the storefront while retaining its content.</p>
                <div class="admin-form-grid">
                    @foreach(['display_name' => 'Client name', 'location' => 'Location'] as $key => $label)
                        <x-admin.field :label="$label" :for="'story-'.$position.'-'.$key" :error="$errors->first('stories.'.$position.'.'.$key)"><input id="story-{{ $position }}-{{ $key }}" name="stories[{{ $position }}][{{ $key }}]" value="{{ old('stories.'.$position.'.'.$key, $story?->getAttribute($key)) }}" maxlength="{{ $key === 'display_name' ? 120 : 160 }}"></x-admin.field>
                    @endforeach
                </div>
                <x-admin.field label="Quote" :for="'story-'.$position.'-quote'" :error="$errors->first('stories.'.$position.'.quote')" help="Plain text, up to 1,000 characters. Keep the quote concise for the three-card layout."><textarea id="story-{{ $position }}-quote" name="stories[{{ $position }}][quote]" rows="4" maxlength="1000">{{ old('stories.'.$position.'.quote', $story?->quote) }}</textarea></x-admin.field>
                <div class="admin-form-grid">
                    <div>
                        <p class="admin-status-label">Portrait</p>
                        <input type="hidden" name="stories[{{ $position }}][image_id]" value="">
                        <x-admin.media-picker :id="'story-portrait-'.$position" :name="'stories['.$position.'][image_id]'" :selected="$selectedMedia[$position]" :error="$errors->first('stories.'.$position.'.image_id')" />
                    </div>
                    <x-admin.field label="Alt text override" :for="'story-'.$position.'-alt'" :error="$errors->first('stories.'.$position.'.alt')" help="Optional when the portrait has default alt text in Media Library. Otherwise, enter alt text here or add the default in Media Library."><input id="story-{{ $position }}-alt" name="stories[{{ $position }}][alt]" value="{{ old('stories.'.$position.'.alt', $alts[$position]) }}" maxlength="500"></x-admin.field>
                </div>
            </section>
        @endforeach
        <x-admin.form-actions>
            <x-slot:secondary><a class="admin-secondary-button" href="{{ route('admin.homepage.edit') }}">Back to Homepage</a></x-slot:secondary>
            <x-slot:primary><button class="admin-primary-button">Save changes</button></x-slot:primary>
        </x-admin.form-actions>
    </form>
</x-admin.layout>
