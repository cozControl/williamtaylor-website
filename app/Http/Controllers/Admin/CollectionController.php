<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalogue\Actions\ArchiveCollectionProduct;
use App\Domain\Catalogue\Actions\AssignCollectionMedia;
use App\Domain\Catalogue\Actions\AssignProductToCollection;
use App\Domain\Catalogue\Actions\ChangeCollectionSlug;
use App\Domain\Catalogue\Actions\CreateCollection;
use App\Domain\Catalogue\Actions\RemoveCollectionMedia;
use App\Domain\Catalogue\Actions\ReorderCollectionProducts;
use App\Domain\Catalogue\Actions\ReviseCollection;
use App\Domain\Catalogue\Actions\UpdateCollectionMediaUsage;
use App\Domain\Catalogue\Actions\UpdateCollectionVisibility;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\CollectionProduct;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Support\CatalogueReadinessEvaluator;
use App\Domain\Catalogue\Support\CollectionMediaRoleRegistry;
use App\Domain\Catalogue\Support\CollectionStateFingerprint;
use App\Domain\Catalogue\Support\ProductPrice;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUsage;
use App\Domain\Media\Queries\ReadyImagePickerQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class CollectionController
{
    public function index(Request $request, MediaProvider $media): View
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status', 'all');
        $collections = Collection::query()->with('currentDraftRevision')->withCount(['products' => fn ($query) => $query->active()])
            ->when($search !== '', fn ($query) => $query->whereHas('currentDraftRevision', fn ($revision) => $revision->where('title', 'like', "%{$search}%")))
            ->when($status === 'visible', fn ($query) => $query->active()->where('catalogue_status', 'ready'))
            ->when($status === 'hidden', fn ($query) => $query->active()->where('catalogue_status', 'draft'))
            ->when($status === 'archived', fn ($query) => $query->whereNotNull('archived_at'))
            ->orderByDesc('updated_at')->paginate(20)->withQueryString();
        $images = MediaUsage::query()->with('asset')->where('owner_type', Collection::class)->whereIn('owner_identifier', $collections->getCollection()->pluck('id'))->where('field_role', CollectionMediaRoleRegistry::CARD)->get()->keyBy('owner_identifier')->map(fn (MediaUsage $usage) => $media->deliveryUrl($usage->asset->provider_public_id, $usage->asset->resource_type->value, 'admin_thumbnail', null, null));

        return view('admin.collections.index', compact('collections', 'images', 'search', 'status'));
    }

    public function create(MediaProvider $media, ReadyImagePickerQuery $images): View
    {
        return $this->editor(new Collection, $media, $images);
    }

    public function store(Request $request, CreateCollection $create): RedirectResponse
    {
        Gate::authorize(PermissionRegistry::PRODUCTS_MANAGE);
        $data = $this->validated($request);
        $collection = DB::transaction(function () use ($request, $create, $data): Collection {
            $collection = $create->handle($request->user(), $data['slug'], ['title' => $data['name'], 'short_description' => $data['description']]);
            $this->sync($request, $collection, $data);

            return $collection;
        }, 3);

        return redirect()->route('admin.collections.index')->with('status', 'Collection created successfully.');
    }

    public function edit(Collection $collection, MediaProvider $media, ReadyImagePickerQuery $images): View
    {
        abort_if($collection->archived_at !== null, 404);

        return $this->editor($collection, $media, $images);
    }

    public function update(Request $request, Collection $collection): RedirectResponse
    {
        Gate::authorize(PermissionRegistry::PRODUCTS_MANAGE);
        abort_if($collection->archived_at !== null, 404);
        $data = $this->validated($request, $collection);
        DB::transaction(function () use ($request, $collection, $data): void {
            if ($collection->slug !== $data['slug']) {
                app(ChangeCollectionSlug::class)->handle($request->user(), $collection, app(CollectionStateFingerprint::class)->identity($collection), $data['slug']);
                $collection = $collection->fresh();
            }
            $revision = $collection->currentDraftRevision;
            if ($revision->title !== $data['name'] || $revision->short_description !== $data['description']) {
                app(ReviseCollection::class)->handle($request->user(), $collection, app(CollectionStateFingerprint::class)->identity($collection->fresh()), ['title' => $data['name'], 'short_description' => $data['description']]);
            }
            $this->sync($request, $collection->fresh(), $data);
        }, 3);

        return redirect()->route('admin.collections.edit', $collection)->with('status', 'Collection updated successfully.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Collection $collection = null): array
    {
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:160', Rule::unique('collections')->ignore($collection?->id)],
            'description' => ['required', 'string', 'max:2000'],
            'visibility' => ['required', Rule::in(['visible', 'hidden'])],
            'media_asset_id' => [
                'nullable',
                'string',
                Rule::exists('media_assets', 'id')->where(fn ($query) => $query
                    ->where('state', MediaAssetState::Ready->value)
                    ->where('resource_type', MediaResourceType::Image->value)
                    ->whereNull('archived_at')),
            ],
            'media_alt' => ['nullable', 'string', 'max:500'],
            'product_ids' => ['array'],
            'product_ids.*' => ['string', 'distinct', Rule::exists('products', 'id')->whereNull('archived_at')],
            'product_order' => ['array'],
            'product_order.*' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'name.required' => 'This field is required.',
            'slug.required' => 'This field is required.',
            'slug.unique' => 'This Collection URL is already in use.',
            'description.required' => 'This field is required.',
            'media_asset_id.exists' => 'The selected Collection image is no longer available.',
            'product_ids.*.exists' => 'The selected Product is no longer available.',
            'product_order.*.integer' => 'Display order must be a whole number.',
            'product_order.*.min' => 'Display order must be zero or greater.',
            'product_order.*.max' => 'Display order may not be greater than 999.',
        ]);

        $mediaId = $data['media_asset_id'] ?? null;
        if ($mediaId !== null) {
            $asset = app(ReadyImagePickerQuery::class)->findEligible($mediaId);
            if ($asset === null) {
                throw ValidationException::withMessages(['media_asset_id' => 'The selected image is no longer available.']);
            }
            $effectiveAlt = trim((string) (($data['media_alt'] ?? null) ?: $asset->default_alt_text));
            if ($effectiveAlt === '' || $effectiveAlt !== strip_tags($effectiveAlt)) {
                throw ValidationException::withMessages(['media_alt' => 'Alt text is required for this Collection image.']);
            }
        }

        $data['media_alt'] = filled($data['media_alt'] ?? null) ? trim((string) $data['media_alt']) : null;

        $productIds = $data['product_ids'] ?? [];
        $productOrder = $data['product_order'] ?? [];
        $selectedOrders = [];
        if (is_array($productIds) && is_array($productOrder)) {
            foreach (array_values($productIds) as $index => $productId) {
                if (is_string($productId)) {
                    $productOrder[$productId] ??= $index;
                    $selectedOrders[] = (int) $productOrder[$productId];
                }
            }
        }
        if (count($selectedOrders) !== count(array_unique($selectedOrders))) {
            throw ValidationException::withMessages(['product_order' => 'Each selected Product must have a unique display order.']);
        }
        $data['product_order'] = $productOrder;

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function sync(Request $request, Collection $collection, array $data): void
    {
        $actor = $request->user();
        $usage = MediaUsage::query()->where('owner_type', Collection::class)->where('owner_identifier', $collection->id)->where('field_role', CollectionMediaRoleRegistry::CARD)->first();
        $mediaId = $data['media_asset_id'] ?? null;
        if ($usage !== null && $usage->media_asset_id !== $mediaId) {
            app(RemoveCollectionMedia::class)->handle($actor, $collection, $usage, app(CollectionStateFingerprint::class)->media($collection->id));
        }
        if ($mediaId !== null && ($usage === null || $usage->media_asset_id !== $mediaId)) {
            try {
                app(AssignCollectionMedia::class)->handle($actor, $collection->fresh(), MediaAsset::query()->whereKey($mediaId)->sole(), app(CollectionStateFingerprint::class)->media($collection->id), CollectionMediaRoleRegistry::CARD, $data['media_alt']);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['media_alt' => 'Alt text is required for this Collection image.']);
            }
        } elseif ($usage !== null && $usage->media_asset_id === $mediaId && $usage->alt_text_override !== $data['media_alt']) {
            try {
                app(UpdateCollectionMediaUsage::class)->handle($actor, $collection->fresh(), $usage, app(CollectionStateFingerprint::class)->media($collection->id), $data['media_alt']);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['media_alt' => 'Alt text is required for this Collection image.']);
            }
        }

        $requested = array_values($data['product_ids'] ?? []);
        usort($requested, fn (string $left, string $right): int => ((int) ($data['product_order'][$left] ?? 999)) <=> ((int) ($data['product_order'][$right] ?? 999)));
        $members = CollectionProduct::query()->active()->where('collection_id', $collection->id)->get();
        foreach ($members as $member) {
            if (! in_array($member->product_id, $requested, true)) {
                app(ArchiveCollectionProduct::class)->handle($actor, $collection->fresh(), $member, app(CollectionStateFingerprint::class)->memberships($collection->id), 'Removed from Collection workspace.');
            }
        }
        foreach ($requested as $productId) {
            if (! CollectionProduct::query()->active()->where('collection_id', $collection->id)->where('product_id', $productId)->exists()) {
                app(AssignProductToCollection::class)->handle($actor, $collection->fresh(), Product::query()->whereKey($productId)->sole(), app(CollectionStateFingerprint::class)->memberships($collection->id));
            }
        }
        $orderedIds = CollectionProduct::query()->active()->where('collection_id', $collection->id)->get()->keyBy('product_id');
        app(ReorderCollectionProducts::class)->handle(
            $actor,
            $collection->fresh(),
            app(CollectionStateFingerprint::class)->memberships($collection->id),
            array_values(collect($requested)->map(fn (string $id): string => $orderedIds[$id]->id)->all()),
            array_values(collect($requested)->map(fn (string $id): int => (int) ($data['product_order'][$id] ?? 999))->all()),
        );
        app(UpdateCollectionVisibility::class)->handle($actor, $collection->fresh(), $data['visibility'] === 'visible' ? 'ready' : 'draft');
    }

    private function editor(Collection $collection, MediaProvider $media, ReadyImagePickerQuery $images): View
    {
        $collection->load(['currentDraftRevision', 'products.product.currentDraftRevision']);
        $products = Product::query()->active()->with(['currentDraftRevision', 'categories'])->orderBy('slug')->get();
        $productReadiness = $products->mapWithKeys(function (Product $product): array {
            $result = app(CatalogueReadinessEvaluator::class)->evaluate($product);
            $labels = [
                'missing_primary_category' => 'Missing category',
                'missing_primary_media' => 'Missing image',
                'unusable_primary_media' => 'Image needs attention',
                'missing_product_price' => 'Missing price',
                'missing_variants' => 'Missing variants',
                'missing_default_variant' => 'Missing default variant',
            ];

            return [$product->id => [
                'ready' => $result->ready,
                'reasons' => array_map(fn (string $code, string $message): string => $labels[$code] ?? Str::headline($message), $result->failureCodes, $result->failureMessages),
                'category' => $product->categories->firstWhere('pivot.is_primary', true)->name ?? $product->categories->first()->name ?? 'Uncategorized',
                'price' => app(ProductPrice::class)->format($product->base_price_minor, $product->currency) ?? 'Price not set',
            ]];
        });
        $usage = $collection->exists ? MediaUsage::query()->with('asset')->where('owner_type', Collection::class)->where('owner_identifier', $collection->id)->where('field_role', CollectionMediaRoleRegistry::CARD)->first() : null;
        $oldSelectedId = old('media_asset_id', $usage === null ? '' : $usage->media_asset_id);
        $selectedId = is_string($oldSelectedId) ? $oldSelectedId : '';
        $selectedAsset = $selectedId === '' ? null : $images->findEligible($selectedId);
        $selectedMedia = $selectedAsset === null ? [] : [[
            'id' => $selectedAsset->id,
            'title' => $selectedAsset->internal_title,
            'filename' => $selectedAsset->original_filename,
            'alt' => (string) $selectedAsset->default_alt_text,
            'thumbnail' => $media->deliveryUrl($selectedAsset->provider_public_id, $selectedAsset->resource_type->value, 'admin_thumbnail', null, null),
        ]];
        $mediaAlt = old('media_alt', $usage === null ? $selectedAsset?->default_alt_text : $usage->alt_text_override);

        return view('admin.collections.form', compact('collection', 'products', 'productReadiness', 'usage', 'selectedMedia', 'mediaAlt'));
    }
}
