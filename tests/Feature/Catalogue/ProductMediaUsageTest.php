<?php

namespace Tests\Feature\Catalogue;

use App\Domain\Catalogue\Actions\AssignProductMedia;
use App\Domain\Catalogue\Actions\CreateProduct;
use App\Domain\Catalogue\Actions\CreateProductRevision;
use App\Domain\Catalogue\Actions\CreateProductVariant;
use App\Domain\Catalogue\Actions\RemoveProductMedia;
use App\Domain\Catalogue\Actions\ReorderProductGallery;
use App\Domain\Catalogue\Actions\SetDefaultProductVariant;
use App\Domain\Catalogue\Actions\SyncProductCatalogueStatus;
use App\Domain\Catalogue\Actions\UpdateProductMediaUsage;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Catalogue\Support\CatalogueReadinessEvaluator;
use App\Domain\Catalogue\Support\ProductMediaAccessibility;
use App\Domain\Catalogue\Support\ProductMediaRoleRegistry;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Support\CategoryOwner;
use Tests\TestCase;

final class ProductMediaUsageTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create();
    }

    public function test_registry_is_code_owned_and_does_not_register_variants(): void
    {
        $registry = app(ProductMediaRoleRegistry::class);
        $this->assertSame(['primary', 'gallery'], $registry->roles(Product::class));
        $this->assertTrue($registry->get(Product::class, 'primary')['singular']);
        $this->assertTrue($registry->get(Product::class, 'gallery')['ordered']);

        $this->expectException(InvalidArgumentException::class);
        $registry->roles('catalogue.variant');
    }

    public function test_assignment_enforces_roles_ownership_duplicates_and_accessibility(): void
    {
        $product = $this->product();
        $primary = $this->image('Primary alt');
        $usage = $this->assign($product, $primary, 'primary');

        $this->assertSame(Product::class, $usage->owner_type);
        $this->assertSame($product->id, $usage->owner_identifier);
        $this->assertFalse($usage->decorative_override);
        $this->assertDatabaseHas('audit_records', ['action' => 'product.media.assigned', 'resource_identifier' => $product->id]);

        foreach ([
            fn () => $this->assign($product->fresh(), $this->image('Another'), 'unsupported'),
            fn () => $this->assign($product->fresh(), $this->image('Another'), 'primary'),
            fn () => $this->assign($product->fresh(), $primary, 'gallery'),
            fn () => $this->assign($product->fresh(), $this->image(''), 'gallery'),
            fn () => $this->assign($product->fresh(), $this->image('<b>bad</b>'), 'gallery'),
            fn () => $this->assign($product->fresh(), $this->image('Useful'), 'gallery', null, true),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Invalid media assignment unexpectedly succeeded.');
            } catch (InvalidArgumentException) {
            }
        }
    }

    public function test_update_resolves_override_then_default_and_rejects_decorative_content(): void
    {
        $product = $this->product();
        $usage = $this->assign($product, $this->image('Default product view'), 'primary', 'Context-specific view');
        $accessibility = app(ProductMediaAccessibility::class);
        $this->assertSame('Context-specific view', $accessibility->effectiveAlt($usage->asset, $usage->alt_text_override));

        $updated = app(UpdateProductMediaUsage::class)->handle(
            $this->actor,
            $product->fresh(),
            $usage,
            $this->state($product),
            null,
        );
        $this->assertSame('Default product view', $accessibility->effectiveAlt($updated->asset, $updated->alt_text_override));

        $this->expectException(InvalidArgumentException::class);
        app(UpdateProductMediaUsage::class)->handle(
            $this->actor,
            $product->fresh(),
            $updated,
            $this->state($product),
            'Still meaningful',
            true,
        );
    }

    public function test_gallery_reorder_is_complete_atomic_idempotent_and_removal_compacts_positions(): void
    {
        $product = $this->product();
        $first = $this->assign($product, $this->image('First'), 'gallery');
        $second = $this->assign($product->fresh(), $this->image('Second'), 'gallery');
        $third = $this->assign($product->fresh(), $this->image('Third'), 'gallery');

        app(ReorderProductGallery::class)->handle($this->actor, $product->fresh(), $this->state($product), [$third->id, $first->id, $second->id]);
        $this->assertSame([$third->id, $first->id, $second->id], $this->galleryIds($product));
        $auditCount = $product->fresh()->lock_version;
        app(ReorderProductGallery::class)->handle($this->actor, $product->fresh(), $this->state($product), [$third->id, $first->id, $second->id]);
        $this->assertSame($auditCount, $product->fresh()->lock_version);

        try {
            app(ReorderProductGallery::class)->handle($this->actor, $product->fresh(), $this->state($product), [$first->id]);
            $this->fail('Partial gallery reorder unexpectedly succeeded.');
        } catch (InvalidArgumentException) {
            $this->assertSame([$third->id, $first->id, $second->id], $this->galleryIds($product));
        }

        app(RemoveProductMedia::class)->handle($this->actor, $product->fresh(), $first, $this->state($product));
        $this->assertSame([$third->id, $second->id], $this->galleryIds($product));
        $this->assertSame([0, 1], $this->gallery($product)->pluck('sort_order')->all());
        $this->assertDatabaseHas('media_assets', ['id' => $first->media_asset_id]);
    }

    public function test_stale_assignment_has_no_mutation(): void
    {
        $product = $this->product();
        $expected = $this->state($product);
        $product->increment('lock_version');

        $this->expectException(StaleCatalogueState::class);
        app(AssignProductMedia::class)->handle($this->actor, $product, $this->image('Alt'), $expected, 'primary');
    }

    public function test_readiness_requires_usable_primary_and_tracks_archive_restore_without_losing_usage(): void
    {
        $product = $this->readyStructure();
        $this->assertContains('missing_primary_media', app(CatalogueReadinessEvaluator::class)->evaluate($product)->failureCodes);

        $asset = $this->image('Front view');
        $usage = $this->assign($product, $asset, 'primary');
        $this->assertSame([], app(CatalogueReadinessEvaluator::class)->evaluate($product->fresh())->failureCodes);
        $this->assertSame('ready', app(SyncProductCatalogueStatus::class)->handle($this->actor, $product->fresh()));

        $asset->update(['state' => MediaAssetState::Archived, 'archived_at' => now()]);
        $result = app(CatalogueReadinessEvaluator::class)->evaluate($product->fresh());
        $this->assertFalse($result->ready);
        $this->assertContains('unusable_primary_media', $result->failureCodes);
        $this->assertDatabaseHas('media_usages', ['id' => $usage->id]);

        $asset->update(['state' => MediaAssetState::Ready, 'archived_at' => null]);
        $this->assertTrue(app(CatalogueReadinessEvaluator::class)->evaluate($product->fresh())->ready);

        $product->forceFill(['catalogue_status' => 'ready'])->save();
        app(RemoveProductMedia::class)->handle($this->actor, $product->fresh(), $usage, $this->state($product));
        $this->assertSame('draft', $product->fresh()->catalogue_status);
    }

    private function product(): Product
    {
        return app(CreateProduct::class)->handle($this->actor, 'product-'.Str::lower(Str::random(8)), 'product-'.Str::lower(Str::random(8)));
    }

    private function readyStructure(): Product
    {
        $product = $this->product();
        app(CreateProductRevision::class)->handle($this->actor, $product, 0, ['title' => 'Tailored jacket', 'features' => []]);
        $product = $product->fresh();
        $variant = app(CreateProductVariant::class)->handle($this->actor, $product, $this->state($product), []);
        $variant->update(['sku' => 'SKU-'.$variant->id]);
        $product = $product->fresh();
        app(SetDefaultProductVariant::class)->handle($this->actor, $product, $variant, $this->state($product));

        $product->update(['base_price_minor' => 10000]);
        $category = ProductCategory::create(['collection_id' => CategoryOwner::for($this->actor->id)->id, 'name' => 'Test category', 'slug' => 'category-'.$product->id, 'is_visible' => true, 'created_by' => $this->actor->id, 'updated_by' => $this->actor->id]);
        $product->categories()->attach($category->id, ['is_primary' => true, 'position' => 0]);

        return $product->fresh();
    }

    private function image(string $alt): MediaAsset
    {
        $id = (string) Str::ulid();

        return MediaAsset::query()->create([
            'id' => $id,
            'provider_asset_id' => 'asset-'.$id,
            'provider_public_id' => 'catalogue/'.$id,
            'resource_type' => MediaResourceType::Image,
            'format' => 'jpg',
            'mime_type' => 'image/jpeg',
            'original_filename' => 'product.jpg',
            'internal_title' => 'Product image',
            'default_alt_text' => $alt,
            'accessibility_classification' => AccessibilityClassification::Informative,
            'is_decorative' => false,
            'state' => MediaAssetState::Ready,
            'bytes' => 1000,
            'uploaded_by' => $this->actor->id,
            'confirmed_at' => now(),
        ]);
    }

    private function assign(Product $product, MediaAsset $asset, string $role, ?string $alt = null, ?bool $decorative = null): MediaUsage
    {
        return app(AssignProductMedia::class)->handle($this->actor, $product, $asset, $this->state($product), $role, $alt, $decorative);
    }

    private function state(Product $product): string
    {
        return app(ProductStateFingerprint::class)->for($product->fresh());
    }

    /** @return Collection<int, MediaUsage> */
    private function gallery(Product $product): Collection
    {
        return MediaUsage::query()->where('owner_type', Product::class)->where('owner_identifier', $product->id)
            ->where('field_role', 'gallery')->orderBy('sort_order')->get();
    }

    /** @return array<int, string> */
    private function galleryIds(Product $product): array
    {
        return $this->gallery($product)->map(fn (MediaUsage $usage): string => $usage->id)->values()->all();
    }
}
