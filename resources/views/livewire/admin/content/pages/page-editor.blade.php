<div class="cms-editor" data-cms-editor data-dirty="{{ $dirty ? 'true' : 'false' }}" x-on:open-page-preview.window="window.open($event.detail.url, '_blank', 'noopener')">
    <div id="draft-feedback" tabindex="-1" class="admin-feedback {{ $feedbackType === 'error' ? 'is-error' : '' }}" role="status" aria-live="assertive" @if(!$feedback) hidden @endif>{{ $feedback }}</div>
    <div class="cms-editor-actions">
        <button class="admin-primary-button" type="button" wire:click="save">Save draft</button>
        @can(\App\Domain\Identity\Support\PermissionRegistry::PAGES_PREVIEW)<button class="admin-secondary-button" type="button" wire:click="preview">Preview saved revision</button>@endcan
        <span>Revision {{ $this->page->currentDraftRevision->revision_number }}</span>
    </div>
    <div class="cms-editor-grid">
        <aside class="admin-panel cms-outline" aria-label="Page section outline">
            <h2>Outline</h2>
            <ol aria-live="polite">@foreach($sections as $index=>$section)<li class="{{ $selected === $index ? 'is-selected' : '' }}"><button type="button" wire:click="selectSection({{ $index }})" aria-pressed="{{ $selected === $index ? 'true' : 'false' }}">{{ $index+1 }}. {{ $registry[$section['type']]['label'] }}</button><div><button type="button" wire:click="moveSection({{ $index }},-1)" aria-label="Move section {{ $index+1 }} up">Up</button><button type="button" wire:click="moveSection({{ $index }},1)" aria-label="Move section {{ $index+1 }} down">Down</button><button type="button" wire:click="duplicateSection({{ $index }})">Duplicate</button><button type="button" wire:click="removeSection({{ $index }})">Remove</button></div></li>@endforeach</ol>
            <div class="admin-field"><label for="add-section">Add approved section</label><select id="add-section" data-add-section><option value="">Choose type</option>@foreach($registry as $key=>$definition)<option value="{{ $key }}">{{ $definition['label'] }}</option>@endforeach</select></div>
            <button type="button" class="admin-secondary-button" data-add-section-button>Add section</button>
        </aside>
        <section class="admin-panel cms-canvas">
            <h2>{{ $registry[$sections[$selected]['type']]['label'] }}</h2>
            <p>{{ $registry[$sections[$selected]['type']]['description'] }}</p>
            @php($type=$sections[$selected]['type'])
            @if(in_array($type,['hero','editorial_split','cta']))
                <div class="admin-field"><label for="section-heading">Heading</label><input id="section-heading" wire:model="sections.{{ $selected }}.data.heading" maxlength="160"></div>
                <div class="admin-field"><label for="section-copy">Supporting copy</label><textarea id="section-copy" wire:model="sections.{{ $selected }}.data.copy" maxlength="1200"></textarea></div>
                @if($type==='hero')<div class="admin-field"><label for="section-eyebrow">Eyebrow</label><input id="section-eyebrow" wire:model="sections.{{ $selected }}.data.eyebrow" maxlength="80"></div>@endif
                <div class="admin-field"><label for="section-media">Existing ready media</label><select id="section-media" wire:model="sections.{{ $selected }}.data.{{ $type==='hero'?'desktop_media':($type==='cta'?'background_media':'media') }}.asset_id"><option value="">No media</option>@foreach($this->media as $asset)<option value="{{ $asset->id }}">{{ $asset->internal_title }} - {{ $asset->width }} x {{ $asset->height }} - {{ $asset->accessibility_classification->value }}</option>@endforeach</select><small>Uploads remain in the Media library. Archived, failed, and processing assets are excluded.</small></div>
                <div class="admin-field"><label for="section-alt">Contextual alt override</label><input id="section-alt" wire:model="sections.{{ $selected }}.data.{{ $type==='hero'?'desktop_media':($type==='cta'?'background_media':'media') }}.alt_override"></div>
                <label class="admin-confirmation"><input type="checkbox" wire:model="sections.{{ $selected }}.data.{{ $type==='hero'?'desktop_media':($type==='cta'?'background_media':'media') }}.decorative"> Decorative in this context</label>
            @elseif($type==='rich_text')
                <div class="cms-rich-text" wire:ignore data-rich-text data-section-index="{{ $selected }}" data-document="{{ json_encode($sections[$selected]['data']['document']) }}">
                    <div class="cms-rich-toolbar" role="toolbar" aria-label="Rich text formatting"><button type="button" data-command="bold">Bold</button><button type="button" data-command="italic">Italic</button><button type="button" data-command="heading2">Heading 2</button><button type="button" data-command="bulletList">Bullet list</button><button type="button" data-command="orderedList">Numbered list</button><button type="button" data-command="blockquote">Quote</button><button type="button" data-command="link">Link</button></div>
                    <div class="cms-rich-surface" data-editor-surface aria-label="Restricted rich text editor"></div>
                    <p>No H1, raw HTML, embedded files, data URLs, scripts, styles, or arbitrary attributes.</p>
                </div>
            @elseif($type==='promotional_cards')
                <div class="admin-field"><label for="cards-heading">Section heading</label><input id="cards-heading" wire:model="sections.{{ $selected }}.data.heading"></div>
                @foreach($sections[$selected]['data']['cards'] as $cardIndex=>$card)<fieldset><legend>Card {{ $cardIndex+1 }}</legend><div class="admin-field"><label>Heading<input wire:model="sections.{{ $selected }}.data.cards.{{ $cardIndex }}.heading"></label></div><div class="admin-field"><label>Copy<textarea wire:model="sections.{{ $selected }}.data.cards.{{ $cardIndex }}.copy"></textarea></label></div></fieldset>@endforeach
            @endif
        </section>
        <aside class="admin-panel cms-context">
            <h2>Draft context</h2><div class="admin-field"><label for="editor-title">Page title</label><input id="editor-title" wire:model="title"></div><div class="admin-field"><label for="editor-slug">Draft slug</label><input id="editor-slug" wire:model="slug"><small>No redirect is created when a draft slug changes.</small></div><div class="admin-field"><label for="editor-template">Template</label><select id="editor-template" wire:model="templateKey">@foreach($templates as $key=>$definition)<option value="{{ $key }}">{{ $definition['label'] }}</option>@endforeach</select></div><div class="admin-field"><label>Locale<input value="English (en)" readonly></label></div><div class="admin-field"><label for="change-summary">Change summary</label><textarea id="change-summary" wire:model="changeSummary" maxlength="2000"></textarea></div>
            <dl><dt>State</dt><dd>Active draft</dd><dt>Expected revision</dt><dd>{{ substr($expectedRevisionId,0,10) }}</dd><dt>Workflow</dt><dd>{{ str_replace('_', ' ', $this->page->publicationState?->candidate_state?->value ?? ($this->page->publicationState?->current_public_revision_id ? 'published' : 'draft')) }}</dd><dt>Candidate</dt><dd>{{ $this->page->publicationState?->candidateRevision?->revision_number ?? 'None' }}</dd><dt>Designated published</dt><dd>{{ $this->page->publicationState?->currentPublicRevision?->revision_number ?? 'None' }}</dd></dl><a class="admin-secondary-button" href="{{ route('admin.content.pages.show', $this->page) }}">Open review and publishing workflow</a>
        </aside>
    </div>
</div>
