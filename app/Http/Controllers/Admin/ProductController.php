<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Actions\ArchiveProduct;
use App\Domain\Catalogue\Actions\AssignProductMedia;
use App\Domain\Catalogue\Actions\ChangeProductSlug;
use App\Domain\Catalogue\Actions\CreateProduct;
use App\Domain\Catalogue\Actions\CreateProductOption;
use App\Domain\Catalogue\Actions\CreateProductOptionValue;
use App\Domain\Catalogue\Actions\CreateProductRevision;
use App\Domain\Catalogue\Actions\CreateProductVariant;
use App\Domain\Catalogue\Actions\RemoveProductMedia;
use App\Domain\Catalogue\Actions\ReorderProductGallery;
use App\Domain\Catalogue\Actions\SetDefaultProductVariant;
use App\Domain\Catalogue\Actions\UpdateProductMediaUsage;
use App\Domain\Catalogue\Actions\UpdateProductOptionValue;
use App\Domain\Catalogue\Actions\UpdateProductVariant;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Catalogue\Models\ProductOptionValue;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Catalogue\Support\CatalogueReadinessEvaluator;
use App\Domain\Catalogue\Support\OxfordProductPresenter;
use App\Domain\Catalogue\Support\ProductMediaRoleRegistry;
use App\Domain\Catalogue\Support\ProductPresenter;
use App\Domain\Catalogue\Support\ProductPrice;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Domain\Catalogue\Support\VariantCombinationFingerprint;
use App\Domain\Catalogue\Support\VariantStateFingerprint;
use App\Domain\Inventory\Services\InventoryAvailabilityService;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUsage;
use App\Domain\Media\Queries\ReadyImagePickerQuery;
use App\Domain\Merchandising\Actions\AddProductRelation;
use App\Domain\Merchandising\Actions\ArchiveProductRelation;
use App\Domain\Merchandising\Actions\ReorderProductRelations;
use App\Domain\Merchandising\Models\ProductRelation;
use App\Domain\Merchandising\Support\ProductRelationSetFingerprint;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class ProductController
{
    public function index(Request $request, MediaProvider $media, ProductPresenter $presenter): View
    {
        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('search'));
        $category = (string) $request->query('category');
        $products = Product::query()->with(['currentDraftRevision', 'categories'])->withCount(['variants' => fn ($query) => $query->whereNull('archived_at')])
            ->when($search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('slug', 'like', "%{$search}%")->orWhereHas('currentDraftRevision', fn ($revision) => $revision->where('title', 'like', "%{$search}%"))))
            ->when($status === 'active', fn ($query) => $query->whereNull('archived_at')->where('catalogue_status', 'ready'))
            ->when($status === 'hidden', fn ($query) => $query->whereNull('archived_at')->where('catalogue_status', 'draft'))
            ->when($status === 'archived', fn ($query) => $query->whereNotNull('archived_at'))
            ->when($category !== '', fn ($query) => $query->whereHas('categories', fn ($categories) => $categories->whereKey($category)))
            ->orderByDesc('updated_at')->orderBy('slug')->paginate(20)->withQueryString();

        $primary = MediaUsage::query()->with('asset')->where('owner_type', Product::class)
            ->whereIn('owner_identifier', $products->getCollection()->pluck('id'))->where('field_role', ProductMediaRoleRegistry::PRIMARY)->get()->keyBy('owner_identifier')
            ->map(fn ($usage) => $media->deliveryUrl($usage->asset->provider_public_id, $usage->asset->resource_type->value, 'admin_thumbnail', null, null));

        $categories = ProductCategory::query()->with(['parent', 'collection.currentDraftRevision'])->whereNull('archived_at')->orderBy('name')->get();
        $storefrontResolvable = $products->getCollection()->mapWithKeys(
            fn (Product $product): array => [$product->id => $presenter->resolve($product->slug) !== null]
        );

        return view('admin.products.index', compact('products', 'primary', 'status', 'search', 'category', 'categories', 'storefrontResolvable'));
    }

    public function create(Request $request, MediaProvider $media, ReadyImagePickerQuery $images): View
    {
        $categories = ProductCategory::query()->with(['parent', 'collection.currentDraftRevision'])->whereNull('archived_at')->orderBy('name')->get();
        $relatedCandidates = Product::query()->active()->where('catalogue_status', 'ready')->with('currentDraftRevision')->orderBy('slug')->get();
        $oldPrimaryMediaId = $request->old('primary_media_id');
        $primarySelected = $this->presentDraftMedia(is_string($oldPrimaryMediaId) ? [$oldPrimaryMediaId] : [], $media, $images);
        $gallerySelected = $this->presentDraftMedia((array) $request->old('gallery_media_ids', []), $media, $images);
        $draftColours = collect((array) $request->old('draft_colours', []))->map(function (array $colour, string $key) use ($media, $images): array {
            return [
                'key' => $key,
                'name' => (string) ($colour['name'] ?? ''),
                'swatch_hex' => (string) ($colour['swatch_hex'] ?? ''),
                'media' => $this->presentDraftMedia((array) ($colour['media_ids'] ?? []), $media, $images),
            ];
        })->values()->all();
        $draftSizes = collect((array) $request->old('draft_sizes', []))->map(fn (array $size, string $key): array => ['key' => $key, 'name' => (string) ($size['name'] ?? '')])->values()->all();
        $draftVariants = array_values((array) $request->old('draft_variants', []));

        return view('admin.products.create', compact('categories', 'relatedCandidates', 'primarySelected', 'gallerySelected', 'draftColours', 'draftSizes', 'draftVariants'));
    }

    public function store(Request $request, CreateProduct $create, CreateProductRevision $revise): RedirectResponse
    {
        $request->merge(['slug' => Str::slug(trim((string) $request->input('slug')) ?: (string) $request->input('title'))]);
        $data = $request->validate($this->createRules(), [
            'slug.unique' => 'This Product URL is already in use. Choose another slug.',
            'draft_variants.required' => 'Generate Variants before saving the Product.',
            'draft_variants.min' => 'Generate at least one Variant before saving the Product.',
            'default_variant_key.required' => 'Choose a default Variant.',
        ]);
        if (in_array($data['slug'], $this->reservedSlugs(), true)) {
            return back()->withInput()->withErrors(['slug' => 'This slug is reserved by an existing storefront or administration route.']);
        }
        $seenSkus = [];
        foreach ($data['draft_variants'] as $index => $variant) {
            $sku = strtoupper(trim((string) $variant['sku']));
            if (in_array($sku, $seenSkus, true) || ProductVariant::query()->where('sku', $sku)->exists()) {
                return back()->withInput()->withErrors(["draft_variants.{$index}.sku" => 'This SKU is already in use.']);
            }
            $seenSkus[] = $sku;
        }
        $productMediaIds = array_values(array_unique(array_filter(array_merge([(string) ($data['primary_media_id'] ?? '')], $data['gallery_media_ids'] ?? []))));
        foreach ($productMediaIds as $mediaId) {
            $asset = app(ReadyImagePickerQuery::class)->findEligible($mediaId);
            if ($asset === null) {
                throw ValidationException::withMessages(['primary_media_id' => 'One or more selected images are no longer available.']);
            }
            $effectiveAlt = trim((string) (($data['media_alt'][$mediaId] ?? null) ?: $asset->default_alt_text));
            if ($effectiveAlt === '' || $effectiveAlt !== strip_tags($effectiveAlt)) {
                throw ValidationException::withMessages(["media_alt.{$mediaId}" => 'Alt text is required for this Product image.']);
            }
        }
        try {
            $product = DB::transaction(function () use ($request, $data, $create, $revise) {
                $actor = $request->user();
                $product = $create->handle($actor, $data['slug'], $data['slug']);
                $revise->handle($actor, $product, 0, [
                    'title' => $data['title'], 'short_description' => $data['short_description'] ?? null,
                    'description_document' => $this->plainTextDocument($data['description'] ?? ''),
                    'materials' => $data['materials'] ?? null, 'fit' => $data['fit'] ?? null, 'care' => $data['care'] ?? null,
                    'features' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['features'] ?? '')) ?: []))),
                ]);
                $product->refresh();
                $prices = app(ProductPrice::class);
                $baseMinor = $prices->parse($data['base_price'], $data['currency']);
                $compareMinor = $prices->parse($data['compare_at_price'] ?? null, $data['currency']);
                if ($baseMinor === null || ($compareMinor !== null && $compareMinor <= $baseMinor)) {
                    throw new InvalidArgumentException('Base price is required and compare-at price must be higher.');
                }
                $product->forceFill(['base_price_minor' => $baseMinor, 'compare_at_price_minor' => $compareMinor, 'currency' => $data['currency'], 'lock_version' => $product->lock_version + 1])->save();
                $categoryIds = array_values(array_unique(array_merge([$data['primary_category_id']], $data['category_ids'] ?? [])));
                $product->categories()->sync(collect($categoryIds)->mapWithKeys(fn ($id, $position) => [$id => ['is_primary' => $id === $data['primary_category_id'], 'position' => $position]])->all());
                $draft = $this->createDraftOptionsAndVariants($actor, $product->fresh(), $data);
                $product->refresh();
                $this->syncMedia($actor, $product, $data);
                $this->syncDraftColourMedia($product, $draft['colours'], $data['draft_colours'] ?? []);
                $product->refresh();
                $defaultKey = (string) ($data['default_variant_key'] ?? '');
                $default = $draft['variants'][$defaultKey] ?? null;
                if ($default === null) {
                    throw ValidationException::withMessages(['default_variant_key' => 'Choose a default Variant.']);
                }
                app(SetDefaultProductVariant::class)->handle($actor, $product, $default, app(ProductStateFingerprint::class)->for($product));
                $product->refresh();
                if ($data['status'] === 'active') {
                    $readiness = app(CatalogueReadinessEvaluator::class)->evaluate($product);
                    if (! $readiness->ready) {
                        throw new InvalidArgumentException('This Product needs attention before it can be Active: '.implode(' ', $this->storefrontReadinessMessages($readiness->failureCodes)));
                    }
                    $product->forceFill(['catalogue_status' => 'ready', 'lock_version' => $product->lock_version + 1])->save();
                    app(RecordAuditEvent::class)->handle('product.visibility.changed', $product, $actor, ['status' => 'draft'], ['status' => 'ready']);
                }

                return $product;
            }, 3);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['product' => $exception->getMessage()]);
        }

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product created successfully.');
    }

    public function edit(Product $product, MediaProvider $media, ReadyImagePickerQuery $images, CatalogueReadinessEvaluator $readiness, ProductPresenter $presenter): View
    {
        $product->load(['currentDraftRevision', 'categories', 'options' => fn ($query) => $query->with('values'), 'variants' => fn ($query) => $query->with('values')]);
        $usages = MediaUsage::query()->with('asset')->where('owner_type', Product::class)->where('owner_identifier', $product->id)->orderBy('sort_order')->get();

        $colourOption = $product->options->firstWhere('key', 'colour');
        $colourValues = $colourOption === null ? collect() : $colourOption->values;
        $colourUsages = MediaUsage::query()->with('asset')->where('owner_type', ProductOptionValue::class)->whereIn('owner_identifier', $colourValues->pluck('id'))->orderBy('sort_order')->get()->groupBy('owner_identifier');
        $present = fn (MediaUsage $usage): array => [
            'id' => $usage->asset->id,
            'title' => $usage->asset->internal_title,
            'filename' => $usage->asset->original_filename,
            'alt' => (string) $usage->asset->default_alt_text,
            'thumbnail' => $media->deliveryUrl($usage->asset->provider_public_id, $usage->asset->resource_type->value, 'admin_thumbnail', null, null),
        ];
        $primarySelected = $usages->where('field_role', ProductMediaRoleRegistry::PRIMARY)->filter(fn (MediaUsage $usage): bool => $images->findEligible($usage->media_asset_id) !== null)->map($present)->values()->all();
        $gallerySelected = $usages->where('field_role', ProductMediaRoleRegistry::GALLERY)->filter(fn (MediaUsage $usage): bool => $images->findEligible($usage->media_asset_id) !== null)->map($present)->values()->all();
        $galleryOrders = $usages->where('field_role', ProductMediaRoleRegistry::GALLERY)->pluck('sort_order', 'media_asset_id')->all();
        $mediaAlts = $usages->pluck('alt_text_override', 'media_asset_id')->all();
        $colourSelected = $colourUsages->map(fn ($items) => $items->filter(fn (MediaUsage $usage): bool => $images->findEligible($usage->media_asset_id) !== null)->map($present)->values()->all());
        $colourOrders = $colourUsages->map(fn ($items) => $items->pluck('sort_order', 'media_asset_id')->all());
        $categories = ProductCategory::query()->with(['parent', 'collection.currentDraftRevision'])->whereNull('archived_at')->orderBy('name')->get();
        $relatedIds = ProductRelation::query()->active()->where('source_product_id', $product->id)->where('relation_kind', 'related')->orderBy('position')->pluck('target_product_id')->all();
        $relatedCandidates = Product::query()->active()->whereKeyNot($product->id)->where('catalogue_status', 'ready')->with('currentDraftRevision')->orderBy('slug')->get();
        $storefrontReadiness = $readiness->evaluate($product);
        $storefrontIssues = $this->storefrontReadinessMessages($storefrontReadiness->failureCodes);
        $storefrontResolvable = $presenter->resolve($product->slug) !== null;

        $inventorySummary = auth()->user()->can('inventory.view') ? app(InventoryAvailabilityService::class)->summaries($product->variants) : [];

        return view('admin.products.edit', compact('inventorySummary', 'product', 'usages', 'primarySelected', 'gallerySelected', 'galleryOrders', 'mediaAlts', 'colourValues', 'colourSelected', 'colourOrders', 'categories', 'relatedIds', 'relatedCandidates', 'storefrontReadiness', 'storefrontIssues', 'storefrontResolvable'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $revision = $product->currentDraftRevision;
        $primaryCategory = $product->categories()->wherePivot('is_primary', true)->first() ?? $product->categories()->first();
        $request->merge([
            'slug' => Str::slug((string) $request->input('slug')),
            'currency' => $request->input('currency', $product->currency ?: 'TZS'),
            'base_price' => $request->input('base_price', app(ProductPrice::class)->majorInput($product->base_price_minor, $product->currency)),
            'primary_category_id' => $request->input('primary_category_id', $primaryCategory?->id),
        ]);
        $data = $request->validate([
            'lock_version' => ['required', 'integer'], 'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:160', Rule::unique('products', 'slug')->ignore($product->id)],
            'short_description' => ['nullable', 'string', 'max:2000'], 'description' => ['nullable', 'string', 'max:10000'],
            'materials' => ['nullable', 'string', 'max:5000'], 'fit' => ['nullable', 'string', 'max:5000'], 'care' => ['nullable', 'string', 'max:5000'],
            'features' => ['nullable', 'string', 'max:5000'], 'status' => ['required', Rule::in(['active', 'hidden', 'archived'])],
            'primary_media_id' => ['nullable', 'string'], 'gallery_media_ids' => ['array', 'max:20'], 'gallery_media_ids.*' => ['string', 'distinct'],
            'gallery_order' => ['array'], 'gallery_order.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'media_alt' => ['array'], 'media_alt.*' => ['nullable', 'string', 'max:500'],
            'option_labels' => ['array'], 'option_labels.*' => ['required', 'string', 'max:100'],
            'variant_skus' => ['array'], 'variant_skus.*' => ['nullable', 'string', 'max:100', 'distinct'],
            'variant_prices' => ['array'], 'variant_prices.*' => ['nullable', 'string', 'max:30'],
            'default_variant_id' => ['nullable', 'string'],
            'base_price' => ['required', 'string', 'max:30'], 'compare_at_price' => ['nullable', 'string', 'max:30'],
            'currency' => ['required', Rule::in(['TZS'])],
            'primary_category_id' => ['required', 'string', Rule::exists('product_categories', 'id')->whereNull('archived_at')],
            'category_ids' => ['array'], 'category_ids.*' => ['string', 'distinct', Rule::exists('product_categories', 'id')->whereNull('archived_at')],
            'colours' => ['nullable', 'string', 'max:3000'], 'sizes' => ['nullable', 'string', 'max:3000'],
            'swatch_hex' => ['array'], 'swatch_hex.*' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'synchronize_variants' => ['nullable', 'boolean'],
            'colour_media' => ['array'], 'colour_media.*' => ['array', 'max:20'], 'colour_media.*.*' => ['string', 'distinct'],
            'colour_media_order' => ['array'], 'colour_media_order.*' => ['array'], 'colour_media_order.*.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'related_product_ids' => ['array', 'max:4'], 'related_product_ids.*' => ['string', 'distinct', Rule::exists('products', 'id')->whereNull('archived_at')->where('catalogue_status', 'ready')],
        ]);
        $productMediaIds = array_values(array_unique(array_filter(array_merge([(string) ($data['primary_media_id'] ?? '')], $data['gallery_media_ids'] ?? []))));
        $colourMediaIds = [];
        foreach ($data['colour_media'] ?? [] as $ids) {
            if (is_array($ids)) {
                $colourMediaIds = array_merge($colourMediaIds, array_values(array_filter($ids, 'is_string')));
            }
        }
        $selectedMediaIds = array_values(array_unique(array_merge($productMediaIds, $colourMediaIds)));
        foreach ($selectedMediaIds as $mediaId) {
            $asset = app(ReadyImagePickerQuery::class)->findEligible($mediaId);
            if ($asset === null) {
                throw ValidationException::withMessages(['primary_media_id' => 'One or more selected images are no longer available.']);
            }
            $effectiveAlt = trim((string) (($data['media_alt'][$mediaId] ?? null) ?: $asset->default_alt_text));
            if (in_array($mediaId, $productMediaIds, true) && ($effectiveAlt === '' || $effectiveAlt !== strip_tags($effectiveAlt))) {
                throw ValidationException::withMessages(["media_alt.{$mediaId}" => 'Alt text is required for this Product image.']);
            }
        }
        if (($product->slug === OxfordProductPresenter::SLUG && $data['slug'] !== OxfordProductPresenter::SLUG)
            || ($product->slug !== $data['slug'] && in_array($data['slug'], $this->reservedSlugs(), true))) {
            return back()->withInput()->withErrors(['slug' => 'This Product URL is protected or reserved.']);
        }

        try {
            DB::transaction(function () use ($request, $product, $data, $revision): void {
                $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
                if ($locked->lock_version !== (int) $data['lock_version']) {
                    throw new StaleCatalogueState;
                }
                if ($locked->archived_at !== null) {
                    throw new InvalidArgumentException('Archived Products are read-only.');
                }

                $optionValueIds = $locked->options()->with('values')->get()->flatMap(fn ($option) => $option->values)->pluck('id')->all();
                if (array_diff(array_keys($data['option_labels'] ?? []), $optionValueIds) !== []) {
                    throw new InvalidArgumentException('One or more Product option values do not belong to this Product.');
                }
                $variantIds = $locked->variants()->pluck('id')->all();
                if (array_diff(array_keys($data['variant_skus'] ?? []), $variantIds) !== []) {
                    throw new InvalidArgumentException('One or more Variants do not belong to this Product.');
                }
                if (filled($data['default_variant_id'] ?? null) && ! in_array($data['default_variant_id'], $variantIds, true)) {
                    throw new InvalidArgumentException('The selected default Variant does not belong to this Product.');
                }

                $actor = $request->user();
                $prices = app(ProductPrice::class);
                $state = app(ProductStateFingerprint::class);
                app(ChangeProductSlug::class)->handle($actor, $locked, $state->for($locked), $data['slug']);
                $locked->refresh();
                app(CreateProductRevision::class)->handle($actor, $locked, $locked->lock_version, [
                    'title' => $data['title'], 'subtitle' => $revision?->subtitle,
                    'short_description' => $data['short_description'] ?? null,
                    'description_document' => $this->plainTextDocument($data['description'] ?? ''),
                    'materials' => $data['materials'] ?? null, 'fit' => $data['fit'] ?? null, 'care' => $data['care'] ?? null,
                    'features' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['features'] ?? '')) ?: []))),
                ]);

                $locked->refresh();
                $baseMinor = $prices->parse($data['base_price'], $data['currency']);
                $compareMinor = $prices->parse($data['compare_at_price'] ?? null, $data['currency']);
                if ($baseMinor === null || ($compareMinor !== null && $compareMinor <= $baseMinor)) {
                    throw new InvalidArgumentException('Base price is required and compare-at price must be higher.');
                }
                $locked->forceFill(['base_price_minor' => $baseMinor, 'compare_at_price_minor' => $compareMinor, 'currency' => $data['currency'], 'lock_version' => $locked->lock_version + 1])->save();
                $categoryIds = array_values(array_unique(array_merge([$data['primary_category_id']], $data['category_ids'] ?? [])));
                $locked->categories()->sync(collect($categoryIds)->mapWithKeys(fn ($id, $position) => [$id => ['is_primary' => $id === $data['primary_category_id'], 'position' => $position]])->all());
                $this->syncOptionsAndVariants($actor, $locked->fresh(), $data);
                $locked->refresh();
                $this->syncOptionLabels($actor, $locked, $data['option_labels'] ?? []);
                $locked->refresh();
                $this->syncVariantSkus($actor, $locked, $data['variant_skus'] ?? [], $data['variant_prices'] ?? []);
                $locked->refresh();
                if (filled($data['default_variant_id'] ?? null) && $locked->default_variant_id !== $data['default_variant_id']) {
                    $default = ProductVariant::query()->active()->where('product_id', $locked->id)->whereKey($data['default_variant_id'])->first();
                    if ($default === null) {
                        throw new InvalidArgumentException('The selected default Variant is unavailable.');
                    }
                    app(SetDefaultProductVariant::class)->handle($actor, $locked, $default, $state->for($locked));
                    $locked->refresh();
                }
                $this->syncMedia($actor, $locked, $data);
                $this->syncColourMedia($locked, $data['colour_media'] ?? [], $data['colour_media_order'] ?? []);
                $locked->refresh();

                if ($data['status'] === 'archived') {
                    app(ArchiveProduct::class)->handle($actor, $locked, $state->for($locked), 'Archived from Product workspace.');
                } else {
                    $next = $data['status'] === 'active' ? 'ready' : 'draft';
                    if ($next === 'ready') {
                        $readiness = app(CatalogueReadinessEvaluator::class)->evaluate($locked);
                        if (! $readiness->ready) {
                            throw new InvalidArgumentException('This Product needs attention before it can be Active: '.implode(' ', $this->storefrontReadinessMessages($readiness->failureCodes)));
                        }
                    }
                    if ($locked->catalogue_status !== $next) {
                        $before = $locked->catalogue_status;
                        $locked->forceFill(['catalogue_status' => $next, 'lock_version' => $locked->lock_version + 1])->save();
                        app(RecordAuditEvent::class)->handle('product.visibility.changed', $locked, $actor, ['status' => $before], ['status' => $next]);
                    }
                }
                if ($data['status'] === 'active') {
                    $this->syncRelatedProducts($actor, $locked->fresh(), $data['related_product_ids'] ?? []);
                }
            }, 3);
        } catch (StaleCatalogueState) {
            return back()->withErrors(['product' => 'This Product was updated by another user. Refresh before saving your changes.']);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['product' => $exception->getMessage()]);
        }

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product updated successfully.');
    }

    public function archive(Request $request, Product $product, ArchiveProduct $archive): RedirectResponse
    {
        try {
            $archive->handle($request->user(), $product, app(ProductStateFingerprint::class)->for($product), 'Archived from Products list.');
        } catch (StaleCatalogueState) {
            return back()->withErrors(['product' => 'This Product was updated by another user. Refresh and try again.']);
        }

        return redirect()->route('admin.products.index')->with('status', 'Product archived.');
    }

    /** @return array<string, mixed> */
    private function createRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'], 'slug' => ['required', 'string', 'min:3', 'max:160', 'unique:products,slug'],
            'short_description' => ['nullable', 'string', 'max:2000'], 'description' => ['nullable', 'string', 'max:10000'],
            'materials' => ['nullable', 'string', 'max:5000'], 'fit' => ['nullable', 'string', 'max:5000'], 'care' => ['nullable', 'string', 'max:5000'],
            'features' => ['nullable', 'string', 'max:5000'], 'status' => ['required', Rule::in(['active', 'hidden'])],
            'base_price' => ['required', 'string', 'max:30'], 'compare_at_price' => ['nullable', 'string', 'max:30'], 'currency' => ['required', Rule::in(['TZS'])],
            'primary_category_id' => ['required', 'string', Rule::exists('product_categories', 'id')->whereNull('archived_at')],
            'category_ids' => ['array'], 'category_ids.*' => ['string', 'distinct', Rule::exists('product_categories', 'id')->whereNull('archived_at')],
            'primary_media_id' => ['nullable', 'string'], 'gallery_media_ids' => ['array', 'max:20'], 'gallery_media_ids.*' => ['string', 'distinct'],
            'gallery_order' => ['array'], 'gallery_order.*' => ['nullable', 'integer', 'min:0', 'max:999'], 'media_alt' => ['array'], 'media_alt.*' => ['nullable', 'string', 'max:500'],
            'draft_colours' => ['array', 'max:20'], 'draft_colours.*.name' => ['required', 'string', 'max:100'],
            'draft_colours.*.swatch_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'draft_colours.*.media_ids' => ['array', 'max:20'], 'draft_colours.*.media_ids.*' => ['string', 'distinct'],
            'draft_colours.*.media_order' => ['array'], 'draft_colours.*.media_order.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'draft_sizes' => ['array', 'max:30'], 'draft_sizes.*.name' => ['required', 'string', 'max:100'],
            'draft_variants' => ['required', 'array', 'min:1', 'max:600'],
            'draft_variants.*.key' => ['required', 'string', 'max:100'], 'draft_variants.*.colour_key' => ['nullable', 'string', 'max:100'],
            'draft_variants.*.size_key' => ['nullable', 'string', 'max:100'], 'draft_variants.*.label' => ['required', 'string', 'max:255'],
            'draft_variants.*.sku' => ['required', 'string', 'max:100', 'distinct'], 'draft_variants.*.price' => ['nullable', 'string', 'max:30'],
            'default_variant_key' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array{type: string, content: list<array<string, mixed>>} */
    private function plainTextDocument(string $text): array
    {
        $paragraphs = array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', $text) ?: [])));

        return ['type' => 'doc', 'content' => array_map(fn ($paragraph) => ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $paragraph]]], $paragraphs)];
    }

    /**
     * @param  array<array-key, mixed>  $ids
     * @return list<array{id: string, title: string, filename: string, alt: string, thumbnail: string}>
     */
    private function presentDraftMedia(array $ids, MediaProvider $media, ReadyImagePickerQuery $images): array
    {
        $ids = array_values(array_unique(array_filter($ids, fn (mixed $id): bool => is_string($id) && $id !== '')));
        $assets = MediaAsset::query()->whereIn('id', $ids)->get()->keyBy('id');

        return array_values(collect($ids)->map(function (string $id) use ($assets, $media, $images): ?array {
            $asset = $assets->get($id);
            if ($asset === null || $images->findEligible($id) === null) {
                return null;
            }

            return [
                'id' => $asset->id,
                'title' => $asset->internal_title,
                'filename' => $asset->original_filename,
                'alt' => (string) $asset->default_alt_text,
                'thumbnail' => $media->deliveryUrl($asset->provider_public_id, $asset->resource_type->value, 'admin_thumbnail', null, null),
            ];
        })->filter()->values()->all());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{colours: array<string, ProductOptionValue>, variants: array<string, ProductVariant>}
     */
    private function createDraftOptionsAndVariants(User $actor, Product $product, array $data): array
    {
        $colourValues = $this->createDraftOptionValues($actor, $product, 'colour', $data['draft_colours'] ?? [], 0);
        $sizeValues = $this->createDraftOptionValues($actor, $product, 'size', $data['draft_sizes'] ?? [], 1);

        $variants = [];
        $prices = app(ProductPrice::class);
        $expected = [];
        $colourKeys = array_keys($colourValues);
        $sizeKeys = array_keys($sizeValues);
        if ($colourKeys !== [] && $sizeKeys !== []) {
            foreach ($colourKeys as $colourKey) {
                foreach ($sizeKeys as $sizeKey) {
                    $expected[] = $colourKey.'--'.$sizeKey;
                }
            }
        } elseif ($colourKeys !== []) {
            $expected = array_map(fn (string $key): string => $key.'--none', $colourKeys);
        } elseif ($sizeKeys !== []) {
            $expected = array_map(fn (string $key): string => 'none--'.$key, $sizeKeys);
        } else {
            $expected = ['none--none'];
        }
        $submitted = array_map(fn (array $row): string => ((string) ($row['colour_key'] ?? '') ?: 'none').'--'.((string) ($row['size_key'] ?? '') ?: 'none'), $data['draft_variants']);
        sort($expected);
        sort($submitted);
        if ($expected !== $submitted) {
            throw ValidationException::withMessages(['draft_variants' => 'Update Variants so every current Colour and Size combination is included once.']);
        }
        foreach ($data['draft_variants'] as $position => $row) {
            $combination = [];
            foreach (['colour_key' => $colourValues, 'size_key' => $sizeValues] as $field => $values) {
                $draftKey = (string) ($row[$field] ?? '');
                if ($draftKey === '') {
                    continue;
                }
                if (! isset($values[$draftKey])) {
                    throw ValidationException::withMessages(["draft_variants.{$position}.{$field}" => 'This Variant references an unavailable option value.']);
                }
                $combination[] = ['option_id' => $values[$draftKey]->product_option_id, 'value_id' => $values[$draftKey]->id];
            }
            $product->refresh();
            $variant = app(CreateProductVariant::class)->handle($actor, $product, app(ProductStateFingerprint::class)->for($product), $combination, (string) $row['sku'], null, (string) $row['label'], $position);
            $override = $prices->parse($row['price'] ?? null, $product->currency);
            if ($override !== null) {
                $product->refresh();
                $variant = app(UpdateProductVariant::class)->handle($actor, $product, $variant, app(ProductStateFingerprint::class)->for($product), app(VariantStateFingerprint::class)->for($product, $variant), $variant->sku, null, $variant->editorial_label, $position, null, $override);
            }
            $variants[(string) $row['key']] = $variant;
        }

        return ['colours' => $colourValues, 'variants' => $variants];
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @return array<string, ProductOptionValue>
     */
    private function createDraftOptionValues(User $actor, Product $product, string $optionKey, array $rows, int $optionPosition): array
    {
        if ($rows === []) {
            return [];
        }

        $option = app(CreateProductOption::class)->handle($actor, $product->fresh(), $product->fresh()->lock_version, $optionKey, ucfirst($optionKey), $optionPosition);
        $values = [];
        $position = 0;
        foreach ($rows as $draftKey => $row) {
            $label = trim((string) $row['name']);
            $value = app(CreateProductOptionValue::class)->handle($actor, $option, Str::slug($label, '_'), $label, $position);
            if ($optionKey === 'colour') {
                $value->forceFill(['swatch_hex' => filled($row['swatch_hex'] ?? null) ? strtoupper((string) $row['swatch_hex']) : null])->save();
            }
            $values[$draftKey] = $value;
            $position++;
        }

        return $values;
    }

    /**
     * @param  array<string, ProductOptionValue>  $colours
     * @param  array<string, array<string, mixed>>  $drafts
     */
    private function syncDraftColourMedia(Product $product, array $colours, array $drafts): void
    {
        $requested = [];
        $orders = [];
        foreach ($colours as $draftKey => $value) {
            $requested[$value->id] = array_values((array) ($drafts[$draftKey]['media_ids'] ?? []));
            $orders[$value->id] = (array) ($drafts[$draftKey]['media_order'] ?? []);
        }
        $this->syncColourMedia($product, $requested, $orders);
    }

    /** @param array<string, string> $labels */
    private function syncOptionLabels(User $actor, Product $product, array $labels): void
    {
        $state = app(ProductStateFingerprint::class);
        $product->load('options.values');
        foreach ($product->options as $option) {
            foreach ($option->values as $value) {
                if (! array_key_exists($value->id, $labels)) {
                    continue;
                }
                app(UpdateProductOptionValue::class)->handle($actor, $product, $option, $value, $state->for($product), $value->key, $labels[$value->id], $value->position);
                $product->refresh();
            }
        }
    }

    /**
     * @param  array<string, string|null>  $skus
     * @param  array<string, string|null>  $prices
     */
    private function syncVariantSkus(User $actor, Product $product, array $skus, array $prices): void
    {
        $product->load('variants');
        foreach ($product->variants as $variant) {
            if (! array_key_exists($variant->id, $skus)) {
                continue;
            }
            app(UpdateProductVariant::class)->handle($actor, $product, $variant, app(ProductStateFingerprint::class)->for($product), app(VariantStateFingerprint::class)->for($product, $variant), $skus[$variant->id], $variant->barcode, $variant->editorial_label, $variant->position, null, app(ProductPrice::class)->parse($prices[$variant->id] ?? null, $product->currency));
            $product->refresh();
        }
    }

    /** @param array<string, mixed> $data */
    private function syncMedia(User $actor, Product $product, array $data): void
    {
        $requested = array_values(array_filter(array_merge([(string) ($data['primary_media_id'] ?? '')], $data['gallery_media_ids'] ?? [])));
        $assets = MediaAsset::query()->whereIn('id', $requested)->where('state', MediaAssetState::Ready)->where('resource_type', MediaResourceType::Image)->whereNotNull('confirmed_at')->whereNull('archived_at')->get()->keyBy('id');
        if ($assets->count() !== count(array_unique($requested))) {
            throw new InvalidArgumentException('One or more selected Media Assets are unavailable, archived, or not ready images.');
        }

        $usages = MediaUsage::query()->where('owner_type', Product::class)->where('owner_identifier', $product->id)->get();
        $wantedPrimary = $data['primary_media_id'] ?? null;
        $wantedGallery = $data['gallery_media_ids'] ?? [];
        $wantedGallery = array_values(array_filter($wantedGallery, fn ($id) => $id !== $wantedPrimary));
        $order = $data['gallery_order'] ?? [];
        usort($wantedGallery, fn ($left, $right) => ((int) ($order[$left] ?? 999)) <=> ((int) ($order[$right] ?? 999)));
        foreach ($usages as $usage) {
            $keep = $usage->field_role === ProductMediaRoleRegistry::PRIMARY ? $usage->media_asset_id === $wantedPrimary : in_array($usage->media_asset_id, $wantedGallery, true);
            if (! $keep) {
                app(RemoveProductMedia::class)->handle($actor, $product, $usage, app(ProductStateFingerprint::class)->for($product));
                $product->refresh();
            }
        }

        $usages = MediaUsage::query()->where('owner_type', Product::class)->where('owner_identifier', $product->id)->get();
        foreach (array_filter([$wantedPrimary]) as $id) {
            if (! $usages->contains('media_asset_id', $id)) {
                app(AssignProductMedia::class)->handle($actor, $product, $assets[$id], app(ProductStateFingerprint::class)->for($product), ProductMediaRoleRegistry::PRIMARY, $data['media_alt'][$id] ?? null, false);
                $product->refresh();
            }
        }
        foreach ($wantedGallery as $id) {
            if (! $usages->contains('media_asset_id', $id)) {
                app(AssignProductMedia::class)->handle($actor, $product, $assets[$id], app(ProductStateFingerprint::class)->for($product), ProductMediaRoleRegistry::GALLERY, $data['media_alt'][$id] ?? null, false);
                $product->refresh();
            }
        }

        $currentUsages = MediaUsage::query()->where('owner_type', Product::class)->where('owner_identifier', $product->id)->get();
        foreach ($currentUsages as $usage) {
            $alt = $data['media_alt'][$usage->media_asset_id] ?? null;
            if ($usage->alt_text_override !== ($alt === null ? null : trim($alt))) {
                app(UpdateProductMediaUsage::class)->handle($actor, $product, $usage, app(ProductStateFingerprint::class)->for($product), $alt, false);
                $product->refresh();
            }
        }

        $galleryIds = array_values(MediaUsage::query()->where('owner_type', Product::class)->where('owner_identifier', $product->id)->where('field_role', ProductMediaRoleRegistry::GALLERY)->get()->sortBy(fn ($usage) => array_search($usage->media_asset_id, $wantedGallery, true))->pluck('id')->map(fn ($id): string => (string) $id)->all());
        app(ReorderProductGallery::class)->handle($actor, $product, app(ProductStateFingerprint::class)->for($product), $galleryIds);
    }

    /** @param array<string, mixed> $data */
    private function syncOptionsAndVariants(User $actor, Product $product, array $data): void
    {
        $requested = [];
        foreach (['colour' => 'colours', 'size' => 'sizes'] as $key => $field) {
            $labels = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', (string) ($data[$field] ?? '')) ?: []))));
            if ($labels === []) {
                continue;
            }
            $product->load('options.values');
            $option = $product->options->firstWhere('key', $key);
            if ($option === null) {
                $option = app(CreateProductOption::class)->handle($actor, $product->fresh(), $product->fresh()->lock_version, $key, ucfirst($key), $key === 'colour' ? 0 : 1);
            }
            foreach ($labels as $position => $label) {
                $cleanLabel = trim(explode('|', $label, 2)[0]);
                $valueKey = Str::slug($cleanLabel, '_');
                $value = $option->values()->where('key', $valueKey)->first();
                if ($value === null) {
                    $value = app(CreateProductOptionValue::class)->handle($actor, $option, $valueKey, $cleanLabel, $position);
                }
                if ($key === 'colour') {
                    $hex = $data['swatch_hex'][$value->id] ?? (explode('|', $label, 2)[1] ?? null);
                    $value->forceFill(['swatch_hex' => filled($hex) ? strtoupper(trim($hex)) : $value->swatch_hex, 'is_active' => true])->save();
                }
                $requested[$option->id][] = $value;
            }
        }

        if (! ($data['synchronize_variants'] ?? false)) {
            return;
        }
        $combinations = [[]];
        foreach ($requested as $optionId => $values) {
            $next = [];
            foreach ($combinations as $combination) {
                foreach ($values as $value) {
                    $next[] = [...$combination, ['option_id' => $optionId, 'value_id' => $value->id]];
                }
            }
            $combinations = $next;
        }
        if ($combinations === [[]]) {
            $combinations = [[]];
        }
        foreach ($combinations as $position => $combination) {
            $product->refresh();
            $fingerprint = app(VariantCombinationFingerprint::class)->for($product, $combination);
            $variant = $product->variants()->where('combination_fingerprint', $fingerprint)->first();
            if ($variant === null) {
                $suffix = collect($combination)->map(fn ($pair) => ProductOptionValue::query()->findOrFail($pair['value_id'])->key)->join('-');
                $sku = strtoupper($product->stable_key).($suffix ? '-'.strtoupper($suffix) : '');
                $variant = app(CreateProductVariant::class)->handle($actor, $product, app(ProductStateFingerprint::class)->for($product), $combination, substr($sku, 0, 100), null, null, $position);
            }
        }
        if ($product->default_variant_id === null) {
            $product->refresh();
            $first = $product->variants()->active()->orderBy('position')->first();
            if ($first !== null) {
                app(SetDefaultProductVariant::class)->handle($actor, $product, $first, app(ProductStateFingerprint::class)->for($product));
            }
        }
    }

    /**
     * @param  array<string, list<string>>  $requested
     * @param  array<string, array<string, int|null>>  $orders
     */
    private function syncColourMedia(Product $product, array $requested, array $orders): void
    {
        $colourValueIds = $product->options()->where('key', 'colour')->with('values')->get()->flatMap->values->pluck('id')->all();
        if (array_diff(array_keys($requested), $colourValueIds) !== []) {
            throw new InvalidArgumentException('One or more Colour values do not belong to this Product.');
        }
        $assetIds = array_values(array_unique(array_merge(...array_values($requested ?: [[]]))));
        $ready = MediaAsset::query()->whereIn('id', $assetIds)->where('state', MediaAssetState::Ready)->whereNull('archived_at')->pluck('id')->all();
        if (array_diff($assetIds, $ready) !== []) {
            throw new InvalidArgumentException('Colour galleries may use only ready active Media Assets.');
        }
        foreach ($requested as $valueId => $ids) {
            MediaUsage::query()->where('owner_type', ProductOptionValue::class)->where('owner_identifier', $valueId)->delete();
            usort($ids, fn (string $left, string $right): int => ((int) ($orders[$valueId][$left] ?? 999)) <=> ((int) ($orders[$valueId][$right] ?? 999)));
            foreach (array_values(array_unique($ids)) as $position => $assetId) {
                MediaUsage::query()->create(['id' => (string) Str::ulid(), 'media_asset_id' => $assetId, 'owner_type' => ProductOptionValue::class, 'owner_identifier' => $valueId, 'field_role' => $position === 0 ? 'colour_primary' : 'colour_gallery', 'locale' => null, 'sort_order' => $position]);
            }
        }
    }

    /** @param list<string> $targetIds */
    private function syncRelatedProducts(User $actor, Product $product, array $targetIds): void
    {
        $targetIds = array_values(array_unique($targetIds));
        $current = ProductRelation::query()->active()->where('source_product_id', $product->id)->where('relation_kind', 'related')->get();
        foreach ($current as $relation) {
            if (! in_array($relation->target_product_id, $targetIds, true)) {
                app(ArchiveProductRelation::class)->handle($actor, $product, $relation, app(ProductRelationSetFingerprint::class)->for($product, 'related'), 'Removed from Product workspace.');
            }
        }
        foreach ($targetIds as $targetId) {
            if (! ProductRelation::query()->active()->where('source_product_id', $product->id)->where('relation_kind', 'related')->where('target_product_id', $targetId)->exists()) {
                app(AddProductRelation::class)->handle($actor, $product, Product::query()->findOrFail($targetId), 'related', app(ProductRelationSetFingerprint::class)->for($product, 'related'));
            }
        }
        $relations = ProductRelation::query()->active()->where('source_product_id', $product->id)->where('relation_kind', 'related')->get()->keyBy('target_product_id');
        app(ReorderProductRelations::class)->handle($actor, $product, 'related', app(ProductRelationSetFingerprint::class)->for($product, 'related'), array_values(collect($targetIds)->map(fn ($id) => $relations[$id]->id)->all()));
    }

    /** @return list<string> */
    private function reservedSlugs(): array
    {
        return ['admin', 'shop', 'cart', 'checkout', 'collections', 'account', 'login', 'register', OxfordProductPresenter::SLUG, 'mercerized-cotton-polo', 'the-dar-es-salaam-linen-suit', 'slim-tapered-chinos', 'the-executive-overcoat'];
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function storefrontReadinessMessages(array $codes): array
    {
        $messages = [
            'product_archived' => 'Restore the Product.',
            'missing_current_revision' => 'Add Product content.',
            'invalid_revision_ownership' => 'Repair the Product content assignment.',
            'missing_product_price' => 'Enter a Product price.',
            'missing_primary_category' => 'Choose a primary Category.',
            'missing_variants' => 'Create at least one Variant.',
            'incomplete_variant_combination' => 'Update the Variant combinations.',
            'missing_default_variant' => 'Choose a default Variant.',
            'invalid_default_variant' => 'Choose an active default Variant with a SKU.',
            'missing_primary_media' => 'Select one primary image.',
            'unusable_primary_media' => 'Choose a ready primary image with alt text.',
            'unusable_gallery_media' => 'Fix gallery image readiness or alt text.',
        ];

        return array_map(fn (string $code): string => $messages[$code] ?? 'Review the Product configuration.', $codes);
    }
}
