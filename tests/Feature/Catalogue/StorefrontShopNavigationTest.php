<?php

namespace Tests\Feature\Catalogue;

use App\Domain\Catalogue\Actions\ReviseCollection;
use App\Domain\Catalogue\Actions\UpdateCollectionVisibility;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Catalogue\Support\CollectionStateFingerprint;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Homepage\Support\HomepageSectionVisibility;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\PublicProjection\Services\ResolvePublicSiteChrome;
use App\Domain\SiteContent\Actions\EnsureSiteContent;
use App\Domain\SiteContent\Actions\SaveSiteContentDraft;
use App\Domain\SiteContent\Services\SiteContentWorkflow;
use App\Domain\SiteContent\Support\SiteContentFingerprint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class StorefrontShopNavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
        $this->manager = User::factory()->create(['email_verified_at' => now()]);
        app(ControlledRoleMutation::class)->run(fn () => $this->manager->assignRole(RoleRegistry::CMS_MANAGER));
        $this->category = ProductCategory::query()->create([
            'name' => 'Explore',
            'slug' => 'explore',
            'is_visible' => true,
            'position' => 0,
            'created_by' => $this->manager->id,
            'updated_by' => $this->manager->id,
        ]);
    }

    public function test_canonical_navigation_order_visibility_rename_and_shared_links(): void
    {
        $late = $this->collection('Late Collection', [], true);
        $early = $this->collection('Early Collection', [], true);
        $arrival = $this->collection('Fresh Selection', [], true);
        $hidden = $this->collection('Hidden Selection', [], false);
        $archived = $this->collection('Archived Selection', [], true);
        $archived->forceFill(['archived_at' => now()])->save();
        $late->forceFill(['navigation_order' => 30])->save();
        $early->forceFill(['navigation_order' => 10])->save();
        HomepageHero::query()->updateOrCreate(['id' => HomepageHero::SINGLETON_ID], [...HomepageHero::defaults(), 'new_arrivals_collection_id' => $arrival->id, 'created_by' => $this->manager->id, 'updated_by' => $this->manager->id]);
        $response = $this->get(route('collections.index'))->assertOk();
        $html = $response->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        foreach (['public-shop-dropdown', 'public-mobile-shop-links'] as $id) {
            $nodes = $xpath->query('//*[@id="root"]//*[@id="'.$id.'"]/a');
            $this->assertSame(['All Collections', 'Early Collection', 'Late Collection', 'New Arrivals', 'Pre-Order', 'Limited Edition'], array_map(fn ($node) => trim($node->textContent), iterator_to_array($nodes)));
            $this->assertSame([route('collections.index'), route('collections.show', $early), route('collections.show', $late), route('collections.show', $arrival), route('preorders.index'), route('limited-edition.index')], array_map(fn ($node) => $node->getAttribute('href'), iterator_to_array($nodes)));
        }
        $cards = $xpath->query('//*[@data-collection-card]');
        $this->assertCount(3, $cards);
        $this->assertStringContainsString('Early Collection', $cards[0]->textContent);
        $this->assertStringContainsString('canonical alt', $xpath->query('//*[@data-collection-card]//img')->item(0)->getAttribute('alt'));
        $response->assertDontSee(route('collections.show', $hidden), false)->assertDontSee(route('collections.show', $archived), false);
        $this->get(route('collections.show', $early))->assertOk()->assertSee('aria-current="page"', false);
        $arrival->forceFill(['slug' => 'fresh-this-week'])->save();
        app(ReviseCollection::class)->handle($this->manager, $early, app(CollectionStateFingerprint::class)->identity($early), ['title' => 'Renamed Collection', 'short_description' => 'Renamed description.']);
        app(UpdateCollectionVisibility::class)->handle($this->manager, $early, 'ready');
        $this->get(route('collections.index'))->assertSeeText('Renamed Collection')->assertSee(route('collections.show', 'fresh-this-week'), false)->assertDontSee(route('collections.show', 'fresh-selection'), false);
        $early->forceFill(['catalogue_status' => 'draft'])->save();
        $this->get(route('collections.index'))->assertDontSeeText('Renamed Collection');
        $this->get(route('collections.show', $early))->assertNotFound();
        $this->get(route('collections.show', $archived))->assertNotFound();
    }

    public function test_shared_header_on_representative_routes_and_no_static_commerce_links(): void
    {
        $arrival = $this->collection('New Arrivals', [], true);
        $product = $this->product('Navigation Product', 'navigation-product', true);
        foreach ([route('home'), route('collections.index'), route('collections.show', $arrival), route('preorders.index'), route('limited-edition.index'), route('products.show', $product)] as $url) {
            $response = $this->get($url)->assertOk()->assertSee('data-canonical-shop-header', false)->assertSee('data-shop-navigation="desktop"', false)->assertSee('data-shop-navigation="mobile"', false)->assertSee('public-shop-header-template', false);
            $dom = new \DOMDocument;
            @$dom->loadHTML($response->getContent());
            $xpath = new \DOMXPath($dom);
            $this->assertCount(1, $xpath->query('//*[@id="root"]//header'));
            $this->assertCount(0, $xpath->query('//*[@id="root"]//header//a[contains(@href,"sort=newest") or contains(@href,"collections/limited-edition") or contains(@href,"collections/unisex")]'));
        }
    }

    public function test_admin_order_is_validated_saved_and_audited(): void
    {
        $collection = $this->collection('Ordered Collection', [], true);
        $payload = ['name' => 'Ordered Collection', 'slug' => $collection->slug, 'description' => 'Canonical Collection description.', 'visibility' => 'visible', 'navigation_order' => -1];
        $this->put(route('admin.collections.update', $collection), $payload)->assertSessionHasErrors('navigation_order');
        $this->put(route('admin.collections.update', $collection), [...$payload, 'navigation_order' => 7])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('collections', ['id' => $collection->id, 'navigation_order' => 7]);
        $this->assertDatabaseHas('audit_records', ['action' => 'collection.navigation_order.updated', 'resource_identifier' => $collection->id]);
        $this->get(route('admin.collections.edit', $collection))->assertOk()->assertSeeText('Storefront order');
    }

    public function test_published_editorial_links_survive_and_legacy_commerce_links_are_suppressed(): void
    {
        config()->set('public_site_content.enabled', true);
        $resource = app(EnsureSiteContent::class)->handle($this->manager, 'primary_navigation', null, 'Navigation');
        $item = fn ($key, $label, $path, $children = []) => ['key' => $key, 'label' => $label, 'link' => ['type' => 'internal_path', 'value' => $path], 'visibility' => 'all', 'new_tab' => false, 'children' => $children];
        $payload = ['items' => [$item('shop', 'Browse', '/collections', [$item('legacy', 'Legacy Unisex', '/collections/unisex'), $item('story', 'Our Story', '/about')]), $item('old-limited', 'Old Limited', '/collections/limited-edition'), $item('editorial', 'Editorial', '/about')]];
        app(SaveSiteContentDraft::class)->handle($this->manager, $resource, $resource->current_draft_revision_id, $payload, 'Navigation fixture');
        $resource = $resource->fresh();
        $workflow = app(SiteContentWorkflow::class);
        $fingerprint = fn () => app(SiteContentFingerprint::class)->for($resource->fresh());
        $workflow->submit($this->manager, $resource, 'Ready', $fingerprint());
        $workflow->approve($this->manager, $resource, 'Approved', $fingerprint());
        $workflow->publish($this->manager, $resource, $fingerprint());
        $savedPayload = $resource->fresh()->currentDraftRevision->payload;
        app()->forgetInstance(ResolvePublicSiteChrome::class);
        $this->get(route('collections.index'))->assertOk()->assertSeeText('Browse')->assertSeeText('Our Story')->assertSeeText('Editorial')->assertDontSeeText('Legacy Unisex')->assertDontSeeText('Old Limited');
        $this->assertSame($savedPayload, $resource->fresh()->currentDraftRevision->payload);
    }

    /** @param list<Product> $products */
    public function test_header_links_follow_homepage_visibility_on_desktop_mobile_and_sync_template(): void
    {
        $arrival = $this->collection('New Arrivals', [], true);
        HomepageHero::query()->updateOrCreate(['id' => HomepageHero::SINGLETON_ID], [...HomepageHero::defaults(), 'new_arrivals_collection_id' => $arrival->id, 'created_by' => $this->manager->id, 'updated_by' => $this->manager->id]);
        $sections = ['explore-collections' => 'Collections', 'new-arrivals' => 'New Arrivals', 'future-style' => 'Pre-Order', 'limited-edition' => 'Limited Edition'];
        foreach ($sections as $section => $label) {
            app(HomepageSectionVisibility::class)->setVisible($this->manager, $section, false);
            foreach (['home', 'collections.index'] as $route) {
                $html = $this->get(route($route))->assertOk()->getContent();
                $dom = new \DOMDocument;
                @$dom->loadHTML($html);
                $xpath = new \DOMXPath($dom);
                foreach (['desktop', 'mobile'] as $surface) {
                    $links = $xpath->query('//*[@data-shop-navigation="'.$surface.'"]/a');
                    $labels = array_map(fn ($node) => trim($node->textContent), iterator_to_array($links));
                    $this->assertNotContains($label, $labels);
                }
            }
            app(HomepageSectionVisibility::class)->setVisible($this->manager, $section, true);
            $html = $this->get(route('home'))->assertOk()->getContent();
            $dom = new \DOMDocument;
            @$dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            foreach (['desktop', 'mobile'] as $surface) {
                $links = $xpath->query('//*[@data-shop-navigation="'.$surface.'"]/a');
                $labels = array_map(fn ($node) => trim($node->textContent), iterator_to_array($links));
                $this->assertContains($label, $labels);
            }
        }
    }

    private function collection(string $name, array $products, bool $visible, string $description = 'Canonical Collection description.', ?string $altOverride = null): Collection
    {
        $image = $this->image(Str::slug($name), $name.' canonical alt');
        $ids = collect($products)->pluck('id')->values()->all();
        $orders = collect($ids)->mapWithKeys(fn (string $id, int $position): array => [$id => $position])->all();
        $this->actingAs($this->manager)->post(route('admin.collections.store'), [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $description,
            'media_asset_id' => $image->id,
            'media_alt' => $altOverride,
            'product_ids' => $ids,
            'product_order' => $orders,
            'visibility' => $visible ? 'visible' : 'hidden',
        ])->assertRedirect();

        return Collection::query()->where('slug', Str::slug($name))->sole();
    }

    private function product(string $title, string $slug, bool $active): Product
    {
        $image = $this->image($slug, $title);
        $this->actingAs($this->manager)->post(route('admin.products.store'), [
            'title' => $title,
            'slug' => $slug,
            'short_description' => 'Canonical Product.',
            'currency' => 'TZS',
            'base_price' => '125,000',
            'primary_category_id' => $this->category->id,
            'primary_media_id' => $image->id,
            'media_alt' => [$image->id => $title],
            'gallery_media_ids' => [],
            'draft_variants' => [[
                'key' => 'none--none',
                'colour_key' => '',
                'size_key' => '',
                'label' => 'Default',
                'sku' => 'WT-'.strtoupper($slug),
                'price' => '',
            ]],
            'default_variant_key' => 'none--none',
            'status' => $active ? 'active' : 'hidden',
        ])->assertRedirect();

        return Product::query()->where('slug', $slug)->sole();
    }

    private function image(string $name, string $alt): MediaAsset
    {
        $id = (string) Str::ulid();

        return MediaAsset::query()->create([
            'id' => $id,
            'provider_asset_id' => 'asset-'.$id,
            'provider_public_id' => 'homepage/'.$id,
            'resource_type' => MediaResourceType::Image,
            'format' => 'jpg',
            'mime_type' => 'image/jpeg',
            'original_filename' => $name.'.jpg',
            'internal_title' => $name,
            'default_alt_text' => $alt,
            'accessibility_classification' => AccessibilityClassification::Informative,
            'is_decorative' => false,
            'state' => MediaAssetState::Ready,
            'bytes' => 1000,
            'uploaded_by' => $this->manager->id,
            'confirmed_at' => now(),
        ]);
    }
}
