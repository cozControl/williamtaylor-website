<x-admin.layout :title="$category->exists ? 'Edit '.$category->name : 'New Category'" description="Define a reusable Product category and its place in the storefront hierarchy." eyebrow="Catalogue" :breadcrumbs="['Categories' => route('admin.product-categories.index'), ($category->exists ? $category->name : 'New') => null]">
    @if (session('status'))<div class="admin-panel" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="admin-panel" role="alert"><strong>Category was not saved.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form class="admin-panel admin-form-grid" method="POST" action="{{ $category->exists ? route('admin.product-categories.update', $category) : route('admin.product-categories.store') }}">
        @csrf
        @if ($category->exists) @method('PUT') @endif
        <div class="admin-section-heading"><p>Category details</p><h2>Storefront classification</h2></div>
        <x-admin.field label="Name" for="category-name" :error="$errors->first('name')"><input id="category-name" name="name" value="{{ old('name', $category->name) }}" required autocomplete="off"></x-admin.field>
        <x-admin.field label="Slug" for="category-slug" help="Generated from the name when left blank." :error="$errors->first('slug')"><input id="category-slug" name="slug" value="{{ old('slug', $category->slug) }}" autocomplete="off"></x-admin.field>
        <x-admin.field label="Parent category" for="category-parent" help="Choose Top level when this Category has no parent." :error="$errors->first('parent_id')"><select id="category-parent" name="parent_id"><option value="">Top level</option>@foreach ($parents as $parent)<option value="{{ $parent->id }}" @selected(old('parent_id', $category->parent_id) === $parent->id)>{{ $parent->name }}</option>@endforeach</select></x-admin.field>
        <x-admin.field class="admin-form-wide" label="Description" for="category-description" help="Explain what shoppers will find in this Category." :error="$errors->first('description')"><textarea id="category-description" name="description" rows="5">{{ old('description', $category->description) }}</textarea></x-admin.field>
        <div class="admin-field admin-form-wide">
            <span class="admin-field-label">Category image</span><span class="admin-field-help">Choose a ready image. Removing this association preserves the Media Asset.</span>
            <x-admin.media-picker id="category-media" name="image_media_asset_id" :selected="$selectedMedia" button-label="Choose media" />
            @error('image_media_asset_id')<small class="admin-field-error" role="alert">{{ $message }}</small>@enderror
        </div>
        <x-admin.field label="Visibility" for="category-visibility" help="Hidden Categories are not shown on the storefront." :error="$errors->first('is_visible')"><select id="category-visibility" name="is_visible"><option value="1" @selected((string) old('is_visible', (int) ($category->is_visible ?? true)) === '1')>Visible</option><option value="0" @selected((string) old('is_visible', (int) ($category->is_visible ?? true)) === '0')>Hidden</option></select></x-admin.field>
        <x-admin.field label="Display order" for="category-position" help="Lower numbers appear first." :error="$errors->first('position')"><input id="category-position" type="number" min="0" name="position" value="{{ old('position', $category->position ?? 0) }}"></x-admin.field>
        <div class="admin-page-actions admin-form-wide"><button class="admin-primary-button">Save Category</button></div>
    </form>
    @if ($category->exists)<form method="POST" action="{{ route('admin.product-categories.archive', $category) }}" onsubmit="return confirm('Archive this Category? Product assignments will be preserved.');">@csrf @method('PATCH')<button class="admin-secondary-button">Archive Category</button></form>@endif
</x-admin.layout>
