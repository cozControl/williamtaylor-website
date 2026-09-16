<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Models\Collection as CatalogueCollection;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Queries\ReadyImagePickerQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ProductCategoryController
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $collectionFilter = $request->validate(['collection' => ['nullable', 'string', Rule::exists('collections', 'id')]])['collection'] ?? '';
        $collections = CatalogueCollection::query()->with('currentDraftRevision')->orderBy('slug')->get();
        $categories = ProductCategory::query()->with(['parent', 'collection.currentDraftRevision'])->withCount('products')
            ->when($collectionFilter !== '', fn ($query) => $query->where('collection_id', $collectionFilter))
            ->when($search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")))
            ->orderByRaw('parent_id is not null')->orderBy('position')->orderBy('name')->paginate(30)->withQueryString();

        return view('admin.product-categories.index', compact('categories', 'search', 'collections', 'collectionFilter'));
    }

    public function create(MediaProvider $media, ReadyImagePickerQuery $images): View
    {
        return $this->editor(new ProductCategory, $media, $images);
    }

    public function store(Request $request, RecordAuditEvent $audit): RedirectResponse
    {
        $category = DB::transaction(function () use ($request, $audit): ProductCategory {
            $data = $this->validated($request);
            $category = ProductCategory::query()->create([...$data, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            $audit->handle('product-category.created', $category, $request->user(), null, ['slug' => $category->slug, 'collection_id' => $category->collection_id, 'visible' => $category->is_visible], PermissionRegistry::PRODUCTS_MANAGE);

            return $category;
        }, 3);

        return redirect()->route('admin.product-categories.edit', $category)->with('status', 'Category saved.');
    }

    public function edit(ProductCategory $productCategory, MediaProvider $media, ReadyImagePickerQuery $images): View
    {
        return $this->editor($productCategory, $media, $images);
    }

    public function update(Request $request, ProductCategory $productCategory, RecordAuditEvent $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $productCategory, $audit): void {
            $productCategory = ProductCategory::query()->lockForUpdate()->findOrFail($productCategory->id);
            $data = $this->validated($request, $productCategory);
            if ($data['collection_id'] !== $productCategory->collection_id) {
                if ($productCategory->children()->exists() || $productCategory->products()->whereDoesntHave('collectionMemberships', fn ($q) => $q->whereNull('archived_at')->where('collection_id', $data['collection_id']))->exists()) {
                    throw ValidationException::withMessages(['collection_id' => 'Every assigned Product must already belong to the destination Collection. Move child Categories explicitly first. Product placements are never moved automatically.']);
                }
            }
            if (($data['parent_id'] ?? null) && $this->wouldCreateCycle($productCategory, $data['parent_id'])) {
                abort(422, 'A Category cannot be moved below its own child.');
            }
            $before = $productCategory->only(['name', 'slug', 'collection_id', 'parent_id', 'description', 'image_media_asset_id', 'is_visible', 'position']);
            $productCategory->fill([...$data, 'updated_by' => $request->user()->id])->save();
            $audit->handle('product-category.updated', $productCategory, $request->user(), $before, $productCategory->only(array_keys($before)), PermissionRegistry::PRODUCTS_MANAGE);
        }, 3);

        return back()->with('status', 'Category saved.');
    }

    public function archive(Request $request, ProductCategory $productCategory, RecordAuditEvent $audit): RedirectResponse
    {
        Gate::authorize(PermissionRegistry::PRODUCTS_MANAGE);
        $before = ['archived_at' => $productCategory->archived_at, 'is_visible' => $productCategory->is_visible];
        $productCategory->forceFill(['archived_at' => now(), 'is_visible' => false, 'updated_by' => $request->user()->id])->save();
        $audit->handle('product-category.archived', $productCategory, $request->user(), $before, ['archived_at' => $productCategory->archived_at, 'is_visible' => false], PermissionRegistry::PRODUCTS_MANAGE);

        return redirect()->route('admin.product-categories.index')->with('status', 'Category archived. Existing Product assignments were preserved.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ProductCategory $category = null): array
    {
        Gate::authorize(PermissionRegistry::PRODUCTS_MANAGE);
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'collection_id' => ['required', 'string', Rule::exists('collections', 'id')->whereNull('archived_at')],
            'slug' => ['required', 'string', 'max:160', Rule::unique('product_categories', 'slug')->where('collection_id', $request->input('collection_id'))->ignore($category?->id)],
            'parent_id' => ['nullable', 'string', Rule::exists('product_categories', 'id')->whereNull('archived_at')->where('collection_id', $request->input('collection_id')), Rule::notIn(array_filter([$category?->id]))],
            'description' => ['nullable', 'string', 'max:5000'],
            'image_media_asset_id' => ['nullable', 'string', Rule::exists('media_assets', 'id')->where(fn ($query) => $query->where('state', MediaAssetState::Ready->value)->where('resource_type', MediaResourceType::Image->value)->whereNull('archived_at'))],
            'is_visible' => ['required', 'boolean'],
            'position' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);
        // Serialize ownership changes with canonical Collection membership operations.
        CatalogueCollection::query()->whereKey($data['collection_id'])->lockForUpdate()->firstOrFail();
        $mediaId = $data['image_media_asset_id'] ?? null;
        if ($mediaId !== null && app(ReadyImagePickerQuery::class)->findEligible($mediaId) === null) {
            throw ValidationException::withMessages(['image_media_asset_id' => 'The selected image is no longer available.']);
        }

        return $data;
    }

    /** @return Collection<int, ProductCategory> */
    private function parents(?ProductCategory $excluding = null): Collection
    {
        return ProductCategory::query()->whereNull('archived_at')->when($excluding, fn ($query) => $query->whereKeyNot($excluding->id))->orderBy('name')->get();
    }

    private function editor(ProductCategory $category, MediaProvider $media, ReadyImagePickerQuery $images): View
    {
        $oldSelectedId = old('image_media_asset_id', $category->image_media_asset_id ?? '');
        $selectedId = is_string($oldSelectedId) ? $oldSelectedId : '';
        $selectedAsset = $selectedId === '' ? null : $images->findEligible($selectedId);
        $selectedMedia = $selectedAsset === null ? [] : [[
            'id' => $selectedAsset->id,
            'title' => $selectedAsset->internal_title,
            'filename' => $selectedAsset->original_filename,
            'alt' => (string) $selectedAsset->default_alt_text,
            'thumbnail' => $media->deliveryUrl($selectedAsset->provider_public_id, $selectedAsset->resource_type->value, 'admin_thumbnail', null, null),
        ]];

        return view('admin.product-categories.form', [
            'category' => $category,
            'collections' => CatalogueCollection::query()->active()->with('currentDraftRevision')->orderBy('slug')->get(),
            'parents' => $this->parents($category->exists ? $category : null),
            'selectedMedia' => $selectedMedia,
        ]);
    }

    private function wouldCreateCycle(ProductCategory $category, string $parentId): bool
    {
        $cursor = ProductCategory::query()->find($parentId);
        while ($cursor !== null) {
            if ($cursor->id === $category->id) {
                return true;
            }
            $cursor = $cursor->parent_id === null ? null : ProductCategory::query()->find($cursor->parent_id);
        }

        return false;
    }
}
