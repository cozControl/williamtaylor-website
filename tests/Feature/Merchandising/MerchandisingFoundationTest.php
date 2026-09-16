<?php

namespace Tests\Feature\Merchandising;

use App\Domain\Catalogue\Actions\ArchiveProduct;
use App\Domain\Catalogue\Actions\ArchiveProductBadge;
use App\Domain\Catalogue\Actions\AssignProductBadge;
use App\Domain\Catalogue\Actions\AssignProductMedia;
use App\Domain\Catalogue\Actions\CreateProduct;
use App\Domain\Catalogue\Actions\CreateProductRevision;
use App\Domain\Catalogue\Actions\CreateProductVariant;
use App\Domain\Catalogue\Actions\ReorderProductBadges;
use App\Domain\Catalogue\Actions\RestoreProduct;
use App\Domain\Catalogue\Actions\RestoreProductBadge;
use App\Domain\Catalogue\Actions\SetDefaultProductVariant;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductBadge;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Catalogue\Support\CatalogueReadinessEvaluator;
use App\Domain\Catalogue\Support\ProductBadgeRegistry;
use App\Domain\Catalogue\Support\ProductBadgeSetFingerprint;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Merchandising\Actions\AddProductRelation;
use App\Domain\Merchandising\Actions\ArchiveProductPlacement;
use App\Domain\Merchandising\Actions\ArchiveProductRelation;
use App\Domain\Merchandising\Actions\AssignProductPlacement;
use App\Domain\Merchandising\Actions\ReorderProductPlacements;
use App\Domain\Merchandising\Actions\ReorderProductRelations;
use App\Domain\Merchandising\Actions\RestoreProductPlacement;
use App\Domain\Merchandising\Actions\RestoreProductRelation;
use App\Domain\Merchandising\Exceptions\StaleMerchandisingState;
use App\Domain\Merchandising\Models\ProductPlacement;
use App\Domain\Merchandising\Models\ProductRelation;
use App\Domain\Merchandising\Support\ActiveOrderingKeys;
use App\Domain\Merchandising\Support\ProductMerchandisingEligibilityEvaluator;
use App\Domain\Merchandising\Support\ProductPlacementSetFingerprint;
use App\Domain\Merchandising\Support\ProductPlacementSlotRegistry;
use App\Domain\Merchandising\Support\ProductRelationKindRegistry;
use App\Domain\Merchandising\Support\ProductRelationSetFingerprint;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

final class MerchandisingFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create();
    }

    public function test_badge_inventory_is_deterministic_and_every_observed_claim_is_deferred(): void
    {
        $registry = app(ProductBadgeRegistry::class);
        $this->assertSame(['new', 'limited', 'pre-order', 'sale', 'sold-out'], array_column($registry->all(), 'key'));
        $this->assertSame($registry->all(), $registry->all());
        foreach (['new', 'limited', 'pre-order', 'sale', 'sold-out'] as $key) {
            try {
                $registry->assignable($key);
                $this->fail('Deferred badge unexpectedly became assignable.');
            } catch (InvalidArgumentException) {
            }
        }
        $this->expectException(InvalidArgumentException::class);
        $registry->get('<script>alert(1)</script>');
    }

    public function test_badge_assignment_fails_closed_without_mutation_or_audit(): void
    {
        $product = $this->readyProduct();
        try {
            app(AssignProductBadge::class)->handle($this->actor, $product, $this->badgeState($product), 'pre-order');
            $this->fail('Campaign badge unexpectedly assigned.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('product_badges', 0);
            $this->assertDatabaseMissing('audit_records', ['action' => 'product.badge.assigned']);
        }
    }

    public function test_badge_order_and_archive_are_governed_while_restore_revalidates_registry(): void
    {
        $product = $this->readyProduct();
        $first = $this->legacyBadge($product, 'limited', 0);
        $second = $this->legacyBadge($product, 'pre-order', 1);

        app(ReorderProductBadges::class)->handle($this->actor, $product, $this->badgeState($product), [$second->id, $first->id]);
        $this->assertSame([$second->id, $first->id], ProductBadge::query()->active()->orderBy('position')->pluck('id')->all());
        app(ArchiveProductBadge::class)->handle($this->actor, $product, $second, $this->badgeState($product), 'Claim retired');
        $this->assertNotNull($second->fresh()->archived_at);
        $this->assertSame(0, $first->fresh()->position);

        $this->expectException(InvalidArgumentException::class);
        app(RestoreProductBadge::class)->handle($this->actor, $product, $second->fresh(), $this->badgeState($product));
    }

    public function test_relation_registry_is_fail_closed_directional_and_nonreciprocal(): void
    {
        $definition = app(ProductRelationKindRegistry::class)->get('related');
        $this->assertTrue($definition['directional']);
        $this->assertFalse($definition['reciprocal']);
        $this->assertSame(4, $definition['maximum']);
        $this->expectException(InvalidArgumentException::class);
        app(ProductRelationKindRegistry::class)->get('upsell');
    }

    public function test_relation_add_rejects_self_and_duplicates_and_never_creates_reciprocal(): void
    {
        $source = $this->readyProduct();
        $target = $this->readyProduct();
        $relation = app(AddProductRelation::class)->handle($this->actor, $source, $target, 'related', $this->relationState($source));
        $this->assertSame($target->id, $relation->target_product_id);
        $this->assertDatabaseMissing('product_relations', ['source_product_id' => $target->id, 'target_product_id' => $source->id]);
        $this->assertDatabaseHas('audit_records', ['action' => 'product.relation.added']);

        foreach ([
            fn () => app(AddProductRelation::class)->handle($this->actor, $source, $source, 'related', $this->relationState($source)),
            fn () => app(AddProductRelation::class)->handle($this->actor, $source, $target, 'related', $this->relationState($source)),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Invalid Product relation unexpectedly succeeded.');
            } catch (InvalidArgumentException) {
                $this->assertDatabaseCount('product_relations', 1);
            }
        }
    }

    public function test_relation_reorder_archive_restore_and_stale_rejection_are_atomic(): void
    {
        $source = $this->readyProduct();
        $first = app(AddProductRelation::class)->handle($this->actor, $source, $this->readyProduct(), 'related', $this->relationState($source));
        $second = app(AddProductRelation::class)->handle($this->actor, $source, $this->readyProduct(), 'related', $this->relationState($source));
        app(ReorderProductRelations::class)->handle($this->actor, $source, 'related', $this->relationState($source), [$second->id, $first->id]);
        $this->assertSame([$second->id, $first->id], $this->relations($source)->pluck('id')->all());

        $stale = $this->relationState($source);
        app(ArchiveProductRelation::class)->handle($this->actor, $source, $second, $stale, 'No longer related');
        try {
            app(ReorderProductRelations::class)->handle($this->actor, $source, 'related', $stale, [$first->id]);
            $this->fail('Stale reorder unexpectedly succeeded.');
        } catch (StaleMerchandisingState) {
            $this->assertSame([$first->id], $this->relations($source)->pluck('id')->all());
        }
        app(RestoreProductRelation::class)->handle($this->actor, $source, $second->fresh(), $this->relationState($source));
        $this->assertSame([$first->id, $second->id], $this->relations($source)->pluck('id')->all());
    }

    public function test_placement_registry_is_product_only_bounded_and_unscheduled(): void
    {
        $slot = app(ProductPlacementSlotRegistry::class)->get('homepage-featured-products');
        $this->assertSame('product', $slot['target_type']);
        $this->assertSame(5, $slot['maximum']);
        $this->assertFalse($slot['scheduling']);
        $this->assertFalse($slot['audience_targeting']);
        $this->assertFalse($slot['locale_targeting']);
        $this->expectException(InvalidArgumentException::class);
        app(ProductPlacementSlotRegistry::class)->get('campaign-hero');
    }

    public function test_placement_assignment_reorder_archive_and_restore_preserve_products(): void
    {
        $slot = 'homepage-featured-products';
        $firstProduct = $this->readyProduct();
        $secondProduct = $this->readyProduct();
        $first = app(AssignProductPlacement::class)->handle($this->actor, $slot, $firstProduct, $this->placementState($slot));
        $second = app(AssignProductPlacement::class)->handle($this->actor, $slot, $secondProduct, $this->placementState($slot));
        app(ReorderProductPlacements::class)->handle($this->actor, $slot, $this->placementState($slot), [$second->id, $first->id]);
        $this->assertSame([$second->id, $first->id], $this->placements($slot)->pluck('id')->all());

        app(ArchiveProductPlacement::class)->handle($this->actor, $slot, $second, $this->placementState($slot), 'Rotate feature');
        $this->assertDatabaseHas('products', ['id' => $secondProduct->id]);
        $this->assertSame(0, $first->fresh()->position);
        app(RestoreProductPlacement::class)->handle($this->actor, $slot, $second->fresh(), $this->placementState($slot));
        $this->assertSame([$first->id, $second->id], $this->placements($slot)->pluck('id')->all());
        $this->assertDatabaseHas('audit_records', ['action' => 'product.placement.restored']);
    }

    public function test_product_archive_preserves_configuration_and_pure_eligibility_fails(): void
    {
        $source = $this->readyProduct();
        $target = $this->readyProduct();
        $relation = app(AddProductRelation::class)->handle($this->actor, $source, $target, 'related', $this->relationState($source));
        $placement = app(AssignProductPlacement::class)->handle($this->actor, 'homepage-featured-products', $source, $this->placementState('homepage-featured-products'));
        $beforeAudits = DB::table('audit_records')->count();
        $beforeStatus = $source->catalogue_status;

        app(ArchiveProduct::class)->handle($this->actor, $source, app(ProductStateFingerprint::class)->for($source), 'Retired');
        $result = app(ProductMerchandisingEligibilityEvaluator::class)->evaluate($source->fresh());
        $this->assertFalse($result->eligible);
        $this->assertContains('product_archived', $result->failureCodes);
        $this->assertDatabaseHas('product_relations', ['id' => $relation->id]);
        $this->assertDatabaseHas('product_placements', ['id' => $placement->id]);
        $this->assertSame($beforeAudits + 1, DB::table('audit_records')->count());

        $archived = $source->fresh();
        app(RestoreProduct::class)->handle($this->actor, $archived, app(ProductStateFingerprint::class)->for($archived));
        $this->assertSame('draft', $source->fresh()->catalogue_status);
        $this->assertNotSame($beforeStatus, $source->fresh()->catalogue_status);
        $this->assertDatabaseHas('product_relations', ['id' => $relation->id]);
        $this->assertDatabaseHas('product_placements', ['id' => $placement->id]);
    }

    public function test_ordered_domain_queries_and_eligibility_have_bounded_query_counts(): void
    {
        $source = $this->readyProduct();
        app(AddProductRelation::class)->handle($this->actor, $source, $this->readyProduct(), 'related', $this->relationState($source));
        app(AssignProductPlacement::class)->handle($this->actor, 'homepage-featured-products', $source, $this->placementState('homepage-featured-products'));
        $this->legacyBadge($source, 'limited', 0);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        ProductBadge::query()->active()->where('product_id', $source->id)->orderBy('position')->get();
        ProductRelation::query()->active()->with('target:id')->where('source_product_id', $source->id)->where('relation_kind', 'related')->orderBy('position')->get();
        ProductPlacement::query()->active()->with('product:id')->where('slot_key', 'homepage-featured-products')->orderBy('position')->get();
        app(ProductMerchandisingEligibilityEvaluator::class)->evaluate($source);
        $this->assertLessThanOrEqual(15, $queries);
    }

    private function readyProduct(): Product
    {
        $key = 'product-'.Str::lower(Str::random(10));
        $product = app(CreateProduct::class)->handle($this->actor, $key, $key);
        app(CreateProductRevision::class)->handle($this->actor, $product, 0, ['title' => 'Test Product', 'features' => []]);
        $product = $product->fresh();
        $variant = app(CreateProductVariant::class)->handle($this->actor, $product, app(ProductStateFingerprint::class)->for($product), []);
        $variant->update(['sku' => 'SKU-'.$variant->id]);
        $product = $product->fresh();
        app(SetDefaultProductVariant::class)->handle($this->actor, $product, $variant, app(ProductStateFingerprint::class)->for($product));
        $product = $product->fresh();
        app(AssignProductMedia::class)->handle($this->actor, $product, $this->image(), app(ProductStateFingerprint::class)->for($product), 'primary');
        $product = $product->fresh();
        $product->update(['base_price_minor' => 10000]);
        $category = ProductCategory::create(['name' => 'Test category', 'slug' => 'category-'.$product->id, 'is_visible' => true, 'created_by' => $this->actor->id, 'updated_by' => $this->actor->id]);
        $product->categories()->attach($category->id, ['is_primary' => true, 'position' => 0]);
        $this->assertSame([], app(CatalogueReadinessEvaluator::class)->evaluate($product->fresh())->failureCodes);
        $product->forceFill(['catalogue_status' => 'ready'])->save();

        return $product->fresh();
    }

    private function image(): MediaAsset
    {
        $id = (string) Str::ulid();

        return MediaAsset::query()->create([
            'id' => $id, 'provider_asset_id' => 'asset-'.$id, 'provider_public_id' => 'catalogue/'.$id,
            'resource_type' => MediaResourceType::Image, 'format' => 'jpg', 'mime_type' => 'image/jpeg',
            'original_filename' => 'product.jpg', 'internal_title' => 'Product image',
            'default_alt_text' => 'Product front view', 'accessibility_classification' => AccessibilityClassification::Informative,
            'is_decorative' => false, 'state' => MediaAssetState::Ready, 'bytes' => 1000,
            'uploaded_by' => $this->actor->id, 'confirmed_at' => now(),
        ]);
    }

    private function legacyBadge(Product $product, string $key, int $position): ProductBadge
    {
        $id = (string) Str::ulid();

        return ProductBadge::query()->create([
            'id' => $id, 'product_id' => $product->id, 'badge_key' => $key, 'position' => $position,
            'active_key' => ActiveOrderingKeys::active($product->id, $key),
            'position_key' => ActiveOrderingKeys::active($product->id, (string) $position),
        ]);
    }

    private function badgeState(Product $product): string
    {
        return app(ProductBadgeSetFingerprint::class)->for($product->fresh());
    }

    private function relationState(Product $source): string
    {
        return app(ProductRelationSetFingerprint::class)->for($source->fresh(), 'related');
    }

    private function placementState(string $slot): string
    {
        return app(ProductPlacementSetFingerprint::class)->for($slot);
    }

    /** @return Collection<int, ProductRelation> */
    private function relations(Product $source): Collection
    {
        return ProductRelation::query()->active()->where('source_product_id', $source->id)->where('relation_kind', 'related')->orderBy('position')->get();
    }

    /** @return Collection<int, ProductPlacement> */
    private function placements(string $slot): Collection
    {
        return ProductPlacement::query()->active()->where('slot_key', $slot)->orderBy('position')->get();
    }
}
