<?php

namespace Tests\Feature\Catalogue;

use App\Domain\Catalogue\Actions\ArchiveCollection;
use App\Domain\Catalogue\Actions\ArchiveCollectionProduct;
use App\Domain\Catalogue\Actions\ArchiveProduct;
use App\Domain\Catalogue\Actions\AssignCollectionMedia;
use App\Domain\Catalogue\Actions\AssignProductToCollection;
use App\Domain\Catalogue\Actions\ChangeCollectionSlug;
use App\Domain\Catalogue\Actions\CreateCollection;
use App\Domain\Catalogue\Actions\CreateProduct;
use App\Domain\Catalogue\Actions\RemoveCollectionMedia;
use App\Domain\Catalogue\Actions\ReorderCollectionProducts;
use App\Domain\Catalogue\Actions\RestoreCollection;
use App\Domain\Catalogue\Actions\RestoreCollectionProduct;
use App\Domain\Catalogue\Actions\ReviseCollection;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\CollectionProduct;
use App\Domain\Catalogue\Models\CollectionRevision;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Queries\CollectionConfigurationQuery;
use App\Domain\Catalogue\Support\CollectionMediaRoleRegistry;
use App\Domain\Catalogue\Support\CollectionReadinessEvaluator;
use App\Domain\Catalogue\Support\CollectionStateFingerprint;
use App\Domain\Catalogue\Support\CollectionTypeRegistry;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

final class CollectionFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create();
        Cache::clear();
    }

    public function test_registry_is_fail_closed_and_campaign_types_are_rejected(): void
    {
        $definition = app(CollectionTypeRegistry::class)->get('curated');
        $this->assertTrue($definition['product_membership']);
        $this->assertTrue($definition['manual_ordering']);
        $this->assertFalse($definition['dynamic_query_rules']);
        $this->assertSame(0, $definition['maximum_nesting_depth']);
        $this->expectException(InvalidArgumentException::class);
        app(CollectionTypeRegistry::class)->get('pre-order');
    }

    public function test_creation_revision_and_slug_changes_are_immutable_stale_safe_and_audited(): void
    {
        $collection = $this->collection();
        $this->assertSame(1, $collection->currentDraftRevision->revision_number);
        $this->assertSame('draft', $collection->catalogue_status);
        $this->assertDatabaseCount('collection_products', 0);
        $this->assertDatabaseCount('media_usages', 0);

        $firstRevision = $collection->currentDraftRevision;
        $revision = app(ReviseCollection::class)->handle($this->actor, $collection, $this->identity($collection), ['title' => 'Summer edit', 'short_description' => 'A refined second edit.']);
        $this->assertSame(2, $revision->revision_number);
        $this->assertSame(2, CollectionRevision::query()->where('collection_id', $collection->id)->count());
        $this->expectException(LogicException::class);
        $firstRevision->update(['title' => 'Mutated']);
    }

    public function test_slug_rules_reject_reserved_and_stale_changes_without_mutation(): void
    {
        $collection = $this->collection();
        $expected = $this->identity($collection);
        app(ChangeCollectionSlug::class)->handle($this->actor, $collection, $expected, 'summer-tailoring');
        $this->assertSame('summer-tailoring', $collection->fresh()->slug);
        try {
            app(ChangeCollectionSlug::class)->handle($this->actor, $collection, $expected, 'another-slug');
            $this->fail('Stale slug change unexpectedly succeeded.');
        } catch (StaleCatalogueState) {
            $this->assertSame('summer-tailoring', $collection->fresh()->slug);
        }
        $this->expectException(InvalidArgumentException::class);
        app(ChangeCollectionSlug::class)->handle($this->actor, $collection->fresh(), $this->identity($collection), 'pre-order');
    }

    public function test_memberships_assign_reorder_archive_restore_and_survive_product_lifecycle(): void
    {
        $collection = $this->collection();
        $one = $this->product();
        $two = $this->product();
        $first = $this->assign($collection, $one);
        $second = $this->assign($collection, $two);
        app(ReorderCollectionProducts::class)->handle($this->actor, $collection, $this->memberships($collection), [$second->id, $first->id]);
        $this->assertSame([$second->id, $first->id], CollectionProduct::query()->active()->where('collection_id', $collection->id)->orderBy('position')->pluck('id')->all());

        app(ArchiveCollectionProduct::class)->handle($this->actor, $collection, $second, $this->memberships($collection), 'Seasonal edit');
        $this->assertSame([0], CollectionProduct::query()->active()->where('collection_id', $collection->id)->pluck('position')->all());
        app(RestoreCollectionProduct::class)->handle($this->actor, $collection, $second->fresh(), $this->memberships($collection));
        $this->assertSame([0, 1], CollectionProduct::query()->active()->where('collection_id', $collection->id)->orderBy('position')->pluck('position')->all());

        app(ArchiveProduct::class)->handle($this->actor, $one, app(ProductStateFingerprint::class)->for($one->fresh()), 'Retired');
        $this->assertDatabaseHas('collection_products', ['id' => $first->id, 'product_id' => $one->id]);
        $this->assertContains('archived_product_target', app(CollectionReadinessEvaluator::class)->evaluate($collection->fresh())->failureCodes);
    }

    public function test_collection_media_requires_card_and_hero_with_effective_alt_and_removal_preserves_asset(): void
    {
        $collection = $this->collection();
        $card = $this->image('Collection card');
        $usage = app(AssignCollectionMedia::class)->handle($this->actor, $collection, $card, $this->media($collection), CollectionMediaRoleRegistry::CARD);
        $this->assertSame(Collection::class, $usage->owner_type);
        $this->assertContains('missing_hero_media', app(CollectionReadinessEvaluator::class)->evaluate($collection->fresh())->failureCodes);
        app(AssignCollectionMedia::class)->handle($this->actor, $collection, $this->image('Collection hero'), $this->media($collection), CollectionMediaRoleRegistry::HERO);

        app(RemoveCollectionMedia::class)->handle($this->actor, $collection, $usage, $this->media($collection));
        $this->assertDatabaseHas('media_assets', ['id' => $card->id]);
        $this->assertContains('missing_card_media', app(CollectionReadinessEvaluator::class)->evaluate($collection->fresh())->failureCodes);
        $this->expectException(InvalidArgumentException::class);
        app(AssignCollectionMedia::class)->handle($this->actor, $collection, $this->image(''), $this->media($collection), CollectionMediaRoleRegistry::CARD);
    }

    public function test_archive_restore_preserves_revision_membership_media_and_stays_draft(): void
    {
        $collection = $this->collection();
        $membership = $this->assign($collection, $this->product());
        $usage = app(AssignCollectionMedia::class)->handle($this->actor, $collection, $this->image('Card'), $this->media($collection), 'card');
        app(ArchiveCollection::class)->handle($this->actor, $collection, $this->identity($collection), 'Retired edit');
        $archived = $collection->fresh();
        $this->assertNotNull($archived->archived_at);
        $this->assertDatabaseHas('collection_products', ['id' => $membership->id]);
        $this->assertDatabaseHas('media_usages', ['id' => $usage->id]);
        app(RestoreCollection::class)->handle($this->actor, $archived, $this->identity($archived));
        $this->assertNull($collection->fresh()->archived_at);
        $this->assertSame('draft', $collection->fresh()->catalogue_status);
    }

    public function test_configuration_query_is_scalar_cached_bounded_and_invalidated_after_commit(): void
    {
        $collection = $this->collection();
        $queries = 0;
        \DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $first = app(CollectionConfigurationQuery::class)->find($collection->id);
        $firstQueryCount = $queries;
        $second = app(CollectionConfigurationQuery::class)->find($collection->id);
        $this->assertSame($first, $second);
        $this->assertSame($firstQueryCount, $queries);
        $this->assertLessThanOrEqual(12, $firstQueryCount);

        app(ChangeCollectionSlug::class)->handle($this->actor, $collection, $this->identity($collection), 'cached-slug-change');
        $fresh = app(CollectionConfigurationQuery::class)->find($collection->id);
        $this->assertSame('cached-slug-change', $fresh['slug']);
        $this->assertStringNotContainsString('public', 'catalogue:collection-configuration:v1:'.$collection->id);
    }

    private function collection(): Collection
    {
        return app(CreateCollection::class)->handle($this->actor, 'collection-'.Str::lower(Str::random(8)), ['title' => 'Seasonal edit', 'short_description' => 'A considered collection.']);
    }

    private function product(): Product
    {
        $key = Str::lower(Str::random(8));

        return app(CreateProduct::class)->handle($this->actor, 'product-'.$key, 'product-'.$key);
    }

    private function assign(Collection $collection, Product $product): CollectionProduct
    {
        return app(AssignProductToCollection::class)->handle($this->actor, $collection, $product, $this->memberships($collection));
    }

    private function identity(Collection $collection): string
    {
        return app(CollectionStateFingerprint::class)->identity($collection->fresh());
    }

    private function memberships(Collection $collection): string
    {
        return app(CollectionStateFingerprint::class)->memberships($collection->id);
    }

    private function media(Collection $collection): string
    {
        return app(CollectionStateFingerprint::class)->media($collection->id);
    }

    private function image(string $alt): MediaAsset
    {
        $id = (string) Str::ulid();

        return MediaAsset::query()->create(['id' => $id, 'provider_asset_id' => 'asset-'.$id, 'provider_public_id' => 'collections/'.$id, 'resource_type' => MediaResourceType::Image, 'format' => 'jpg', 'mime_type' => 'image/jpeg', 'original_filename' => 'collection.jpg', 'internal_title' => 'Collection image', 'default_alt_text' => $alt, 'accessibility_classification' => AccessibilityClassification::Informative, 'is_decorative' => false, 'state' => MediaAssetState::Ready, 'bytes' => 1000, 'uploaded_by' => $this->actor->id, 'confirmed_at' => now()]);
    }
}
