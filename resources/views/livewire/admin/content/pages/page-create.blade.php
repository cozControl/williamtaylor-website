<section class="admin-panel cms-create-panel">
    <div id="create-errors" role="alert">
        @if($errors->any())<h2>Correct the page details</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    </div>
    <form wire:submit="create" class="cms-create-form">
        <div class="admin-field"><label for="create-type">Page type</label><select id="create-type" wire:model.live="type">@foreach($types as $key=>$definition)<option value="{{ $key }}">{{ $definition['label'] }}</option>@endforeach</select><small>{{ $types[$type]['description'] }}</small></div>
        <div class="admin-field"><label for="create-title">Page title</label><input id="create-title" wire:model="title" maxlength="255" required></div>
        <div class="admin-field"><label for="create-slug">Draft slug</label><input id="create-slug" wire:model="slug" maxlength="160" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" required><small>Lowercase words separated by single hyphens. Changes do not create redirects in this phase.</small></div>
        <div class="admin-field"><label for="create-locale">Locale</label><input id="create-locale" value="English (en)" readonly><small>The schema is locale-aware; English is the only approved locale.</small></div>
        <div class="admin-field"><label for="create-template">Template</label><select id="create-template" wire:model="templateKey">@foreach($types[$type]['templates'] as $key)<option value="{{ $key }}">{{ $templates[$key]['label'] }}</option>@endforeach</select></div>
        <button class="admin-primary-button">Create initial draft</button>
    </form>
</section>
