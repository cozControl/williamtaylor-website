<?php

namespace Tests\Support;

use App\Domain\Catalogue\Actions\AssignCollectionMedia;
use App\Domain\Catalogue\Actions\AssignProductMedia;
use App\Domain\Catalogue\Actions\AssignProductToCollection;
use App\Domain\Catalogue\Actions\CreateCollection;
use App\Domain\Catalogue\Actions\CreateProduct;
use App\Domain\Catalogue\Actions\CreateProductOption;
use App\Domain\Catalogue\Actions\CreateProductOptionValue;
use App\Domain\Catalogue\Actions\CreateProductRevision;
use App\Domain\Catalogue\Actions\CreateProductVariant;
use App\Domain\Catalogue\Actions\SetDefaultProductVariant;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Catalogue\Support\CatalogueReadinessEvaluator;
use App\Domain\Catalogue\Support\CollectionStateFingerprint;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Domain\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Str;

final class CollectionCatalogueFixture
{
    public function __construct(public User $actor) {}

    public function collection(string $slug): Collection
    {
        $collection = app(CreateCollection::class)->handle($this->actor, $slug, ['title' => Str::title(str_replace('-', ' ', $slug)), 'short_description' => 'Pieces selected for '.$slug.'.']);
        app(AssignCollectionMedia::class)->handle($this->actor, $collection, $this->image(), app(CollectionStateFingerprint::class)->media($collection->id), 'card');
        $collection->refresh()->update(['catalogue_status' => 'ready']);

        return $collection->fresh();
    }

    public function category(Collection $collection, string $slug): ProductCategory
    {
        return ProductCategory::create(['collection_id' => $collection->id, 'slug' => $slug, 'name' => Str::title(str_replace('-', ' ', $slug)), 'created_by' => $this->actor->id, 'updated_by' => $this->actor->id]);
    }

    public function product(Collection $collection, ProductCategory $category, string $slug, ?string $size = null, int $price = 12500000): Product
    {
        $product = app(CreateProduct::class)->handle($this->actor, $slug, $slug);
        app(CreateProductRevision::class)->handle($this->actor, $product, 0, ['title' => Str::title(str_replace('-', ' ', $slug)), 'features' => []]);
        $product->refresh();
        $combination = [];
        if ($size !== null) {
            $option = app(CreateProductOption::class)->handle($this->actor, $product, $product->lock_version, 'size', 'Size', 0);
            $value = app(CreateProductOptionValue::class)->handle($this->actor, $option, strtolower($size), $size, 0);
            $combination = [['option_id' => $option->id, 'value_id' => $value->id]];
        }
        $product->refresh();
        $variant = app(CreateProductVariant::class)->handle($this->actor, $product, app(ProductStateFingerprint::class)->for($product), $combination, 'WT-'.$slug);
        $product->refresh();
        app(SetDefaultProductVariant::class)->handle($this->actor, $product, $variant, app(ProductStateFingerprint::class)->for($product));
        $product->refresh();
        app(AssignProductMedia::class)->handle($this->actor, $product, $this->image(), app(ProductStateFingerprint::class)->for($product), 'primary');
        $product->categories()->attach($category->id, ['is_primary' => true, 'position' => 0]);
        $product->refresh()->update(['base_price_minor' => $price, 'catalogue_status' => 'ready']);
        if (! app(CatalogueReadinessEvaluator::class)->evaluate($product->fresh())->ready) {
            throw new \RuntimeException('Catalogue fixture must satisfy canonical readiness.');
        }
        $this->assign($collection, $product);

        return $product->fresh();
    }

    public function assign(Collection $collection, Product $product): void
    {
        app(AssignProductToCollection::class)->handle($this->actor, $collection, $product, app(CollectionStateFingerprint::class)->memberships($collection->id));
    }

    public function image(): MediaAsset
    {
        $key = (string) Str::ulid();

        return MediaAsset::create(['provider_asset_id' => $key, 'provider_public_id' => 'catalogue-fixture/'.$key, 'resource_type' => 'image', 'format' => 'jpg', 'mime_type' => 'image/jpeg', 'original_filename' => 'shirt.jpg', 'internal_title' => 'Catalogue shirt', 'default_alt_text' => 'Catalogue shirt', 'accessibility_classification' => 'informative', 'is_decorative' => false, 'state' => 'ready', 'bytes' => 1000, 'uploaded_by' => $this->actor->id, 'confirmed_at' => now()]);
    }
}
