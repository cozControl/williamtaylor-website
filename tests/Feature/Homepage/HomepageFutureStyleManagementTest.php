<?php

namespace Tests\Feature\Homepage;

use App\Domain\Campaign\Actions\ReviseCampaign;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Support\CampaignStateFingerprint;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\CategoryOwner;
use Tests\TestCase;

final class HomepageFutureStyleManagementTest extends TestCase
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
        $this->category = ProductCategory::query()->create(['collection_id' => CategoryOwner::for($this->manager->id)->id, 'name' => 'Pre-Order', 'slug' => 'pre-order-products', 'is_visible' => true, 'position' => 0, 'created_by' => $this->manager->id, 'updated_by' => $this->manager->id]);
    }

    public function test_campaign_admin_is_authorized_and_validation_preserves_input(): void
    {
        $this->get(route('admin.campaigns.index'))->assertRedirect(route('login'));
        $ordinary = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($ordinary)->get(route('admin.campaigns.index'))->assertForbidden();
        $this->actingAs($this->manager)->get(route('admin.campaigns.create'))->assertOk()->assertSeeText('Choose Campaign type');
        $this->actingAs($this->manager)->post(route('admin.campaigns.store'), ['campaign_type' => 'pre_order', 'internal_code' => 'future-one', 'headline' => 'Preserved headline'])
            ->assertSessionHasErrors(['summary', 'product_id', 'media_asset_id', 'starts_at'])->assertSessionHasInput('headline', 'Preserved headline');
    }

    public function test_approved_campaign_projects_to_homepage_and_preorder_page(): void
    {
        $product = $this->product('Future Jacket', 'future-jacket', '890,000');
        $campaignImage = $this->image('Campaign presentation');
        $payload = [
            'campaign_type' => 'pre_order',
            'internal_code' => 'future-jacket-launch', 'headline' => 'Future Jacket Campaign', 'summary' => 'A structural masterpiece from the coming collection.', 'cta_label' => 'Reserve Yours',
            'product_id' => $product->id, 'media_asset_id' => $campaignImage->id, 'media_alt_override' => 'Future Jacket campaign presentation',
            'starts_at' => now('Africa/Nairobi')->subDay()->format('Y-m-d\TH:i'), 'ends_at' => now('Africa/Nairobi')->addDays(10)->format('Y-m-d\TH:i'), 'estimated_delivery_date' => now('Africa/Nairobi')->addDays(20)->format('Y-m-d'),
            'public_window_statement' => 'Available during the published Campaign window.', 'evidence_reference' => 'policy:pre-order-window', 'evidence_summary' => 'Approved launch calendar.',
        ];
        $this->actingAs($this->manager)->post(route('admin.campaigns.store'), $payload)->assertRedirect();
        $campaign = Campaign::query()->sole();
        $this->assertSame('in_review', $campaign->claims()->sole()->approval_status);
        $this->actingAs($this->manager)->put(route('admin.campaigns.update', $campaign), [...$payload, 'headline' => 'Future Jacket Release'])->assertSessionHas('status');
        $this->assertSame('Future Jacket Release', $campaign->fresh()->currentDraftRevision->headline);

        app(ControlledRoleMutation::class)->run(fn () => $this->manager->assignRole(RoleRegistry::SUPER_ADMINISTRATOR));
        $this->actingAs($this->manager)->post(route('admin.campaigns.approve', $campaign))->assertSessionHas('status');

        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk();
        $homepage = HomepageHero::query()->sole();
        $this->actingAs($this->manager)->put(route('admin.homepage.future-style.update'), [
            'lock_version' => $homepage->lock_version, 'future_style_managed' => '1', 'future_style_eyebrow' => 'Exclusive Access', 'future_style_heading' => 'The Future of Style',
            'future_style_intro' => 'Tomorrow, tailored today.', 'future_style_cta_label' => 'View All Pre-Orders', 'future_style_campaign_1_id' => $campaign->id, 'future_style_campaign_2_id' => null,
        ])->assertRedirect(route('admin.homepage.future-style.edit'));

        $this->get(route('home'))->assertOk()->assertSee('data-homepage-future-style', false)->assertSeeText('Future Jacket')->assertSeeText('TZS 890,000')->assertSeeText('Ships')->assertSee('data-countdown-target', false)->assertSee(route('preorders.index'), false)->assertSee('homepage-future-style-projection', false);
        $this->get(route('preorders.index'))->assertOk()->assertSeeText('Future Jacket')->assertSeeText('Estimated Delivery:')->assertSee(route('products.show', $product), false)->assertDontSee('/website/js/index-DxdnTNDA.js', false);
    }

    public function test_exact_slots_update_without_cache_clear_and_duplicate_save_is_rejected(): void
    {
        $a = $this->campaign('Alpha');
        $b = $this->campaign('Bravo');
        $c = $this->campaign('Charlie');
        $this->saveSlots($a, $b)->assertSessionHasNoErrors();
        $stored = HomepageHero::query()->sole();
        $this->assertSame($a->id, $stored->future_style_campaign_1_id);
        $this->assertSame($b->id, $stored->future_style_campaign_2_id);
        $this->assertSlots([$a->id, $b->id]);
        $this->saveSlots($a, $c)->assertSessionHasNoErrors();
        $this->assertSlots([$a->id, $c->id]);
        $this->saveSlots($a, $a)->assertSessionHasErrors(['future_style_campaign_1_id', 'future_style_campaign_2_id']);
        $errors = session('errors');
        $this->assertStringContainsString('must be different', view('admin.homepage.future-style', [
            'homepage' => HomepageHero::query()->sole(), 'campaigns' => collect([$a, $b, $c]), 'errors' => $errors,
        ])->render());
        $this->assertSlots([$a->id, $c->id]);
    }

    public function test_ineligible_saved_slots_are_omitted_and_rejected_by_editor(): void
    {
        $a = $this->campaign('Alpha');
        $b = $this->campaign('Bravo');
        $this->campaign('Unselected');
        $this->saveSlots($a, $b)->assertSessionHasNoErrors();
        foreach ([
            ['campaign_type' => 'limited_edition'],
            ['archived_at' => now()],
            ['approved_revision_id' => null],
            ['starts_at' => now()->addDay()],
            ['ends_at' => now()->subMinute()],
        ] as $invalid) {
            $original = $b->only(array_keys($invalid));
            $b->forceFill($invalid)->save();
            $this->assertSlots([$a->id]);
            $this->saveSlots($a, $b)->assertSessionHasErrors('future_style_campaign_2_id');
            $this->get(route('admin.homepage.future-style.edit'))->assertDontSee('value="'.$b->id.'"', false);
            $b->forceFill($original)->save();
        }
        HomepageHero::query()->sole()->forceFill(['future_style_campaign_2_id' => null])->save();
        $this->assertSlots([$a->id]);
    }

    public function test_managed_flag_controls_text_but_campaigns_always_use_database(): void
    {
        $a = $this->campaign('Alpha');
        $this->saveSlots($a, null)->assertSessionHasNoErrors();
        $this->get(route('home'))->assertSeeText('Managed eyebrow')->assertSeeText('Managed heading')->assertSeeText('Managed introduction')->assertSeeText('Managed view all');
        HomepageHero::query()->sole()->forceFill(['future_style_managed' => false])->save();
        $response = $this->get(route('home'))->assertDontSeeText('Managed heading');
        $this->assertFalse($response->viewData('homepageFutureStyle')['managed']);
        $this->assertSame([$a->id], array_column($response->viewData('homepageFutureStyle')['campaigns'], 'id'));
        $this->assertSame($a->id, HomepageHero::query()->sole()->future_style_campaign_1_id);
    }

    public function test_unconfigured_homepage_uses_database_campaigns_and_live_fields(): void
    {
        $a = $this->campaign('DatabaseAlpha');
        $b = $this->campaign('DatabaseBravo');
        $response = $this->get(route('home'))->assertOk()->assertSeeText('DatabaseAlpha')->assertSeeText('DatabaseBravo');
        $section = $response->viewData('homepageFutureStyle');
        $this->assertEqualsCanonicalizing([$a->id, $b->id], array_column($section['campaigns'], 'id'));
        $this->assertSame([], $response->viewData('homepageLimitedEdition')['campaigns']);
        $html = view('frontend.partials.homepage-future-style', ['homepageFutureStyle' => $section])->render();
        $this->assertStringNotContainsString('The Executive Overcoat', $html);
        $this->assertStringNotContainsString('Summer Linen Trousers', $html);
        $card = collect($section['campaigns'])->firstWhere('id', $a->id);
        $this->assertStringContainsString(e($card['image']), $html);
        $this->assertStringContainsString($a->ends_at->toIso8601String(), $html);
        $this->assertStringContainsString($a->estimated_delivery_date->format('Y-m-d'), $html);
        $this->assertStringContainsString(e($card['url']), $html);

        app(ReviseCampaign::class)->handle($this->manager, $a, app(CampaignStateFingerprint::class)->identity($a), [
            'headline' => 'Database headline updated', 'summary' => $a->approvedRevision->summary, 'cta_label' => $a->approvedRevision->cta_label,
        ]);
        $this->post(route('admin.campaigns.approve', $a))->assertSessionHas('status');
        $product = $a->products()->firstOrFail()->product;
        $product->forceFill(['base_price_minor' => 1234500])->save();
        $this->get(route('home'))->assertSeeText('Database headline updated')->assertDontSeeText('DatabaseAlpha')->assertSeeText('TZS 12,345');
        $this->get(route('preorders.index'))->assertSeeText('Database headline updated')->assertDontSeeText('DatabaseAlpha');
        $this->get(route('limited-edition.index'))->assertDontSeeText('Database headline updated');
        $a->forceFill(['archived_at' => now()])->save();
        $b->forceFill(['archived_at' => now()])->save();
        $this->assertSame([], $this->get(route('home'))->viewData('homepageFutureStyle')['campaigns']);
    }

    private function assertSlots(array $ids): void
    {
        $response = $this->get(route('home'))->assertOk();
        $section = $response->viewData('homepageFutureStyle');
        $this->assertSame($ids, array_column($section['campaigns'], 'id'));
        $html = view('frontend.partials.homepage-future-style', ['homepageFutureStyle' => $section])->render();
        foreach ($section['campaigns'] as $campaign) {
            $this->assertStringContainsString($campaign['headline'], $html);
        }
        $this->assertStringNotContainsString('Summer Linen Trousers', $html);
    }

    private function saveSlots(Campaign $a, ?Campaign $b): TestResponse
    {
        $this->actingAs($this->manager)->get(route('admin.homepage.future-style.edit'))->assertOk();

        return $this->put(route('admin.homepage.future-style.update'), [
            ...HomepageHero::futureStyleDefaults(), 'lock_version' => HomepageHero::query()->sole()->lock_version,
            'future_style_managed' => '1', 'future_style_eyebrow' => 'Managed eyebrow', 'future_style_heading' => 'Managed heading',
            'future_style_intro' => 'Managed introduction', 'future_style_cta_label' => 'Managed view all',
            'future_style_campaign_1_id' => $a->id, 'future_style_campaign_2_id' => $b?->id,
        ]);
    }

    private function campaign(string $name): Campaign
    {
        $product = $this->product($name, strtolower($name), '890,000');
        $image = $this->image($name);
        $this->actingAs($this->manager)->post(route('admin.campaigns.store'), [
            'campaign_type' => 'pre_order', 'internal_code' => strtolower($name), 'headline' => $name, 'summary' => 'Approved upcoming collection.', 'cta_label' => 'Reserve Yours',
            'product_id' => $product->id, 'media_asset_id' => $image->id, 'media_alt_override' => $name,
            'starts_at' => now('Africa/Nairobi')->subDay()->format('Y-m-d\TH:i'), 'ends_at' => now('Africa/Nairobi')->addDays(10)->format('Y-m-d\TH:i'),
            'estimated_delivery_date' => now()->addDays(20)->format('Y-m-d'),
            'public_window_statement' => 'Available during the published Campaign window.', 'evidence_reference' => 'policy:pre-order-window', 'evidence_summary' => 'Approved launch calendar.',
        ])->assertSessionHasNoErrors();
        $campaign = Campaign::query()->where('internal_code', strtolower($name))->sole();
        app(ControlledRoleMutation::class)->run(fn () => $this->manager->assignRole(RoleRegistry::SUPER_ADMINISTRATOR));
        $this->post(route('admin.campaigns.approve', $campaign))->assertSessionHas('status');

        return $campaign->fresh();
    }

    private function product(string $title, string $slug, string $price): Product
    {
        $image = $this->image($slug);
        $this->actingAs($this->manager)->post(route('admin.products.store'), [
            'title' => $title, 'slug' => $slug, 'short_description' => 'Canonical Product.', 'currency' => 'TZS', 'base_price' => $price,
            'primary_category_id' => $this->category->id, 'primary_media_id' => $image->id, 'media_alt' => [$image->id => $title], 'gallery_media_ids' => [],
            'draft_variants' => [['key' => 'none--none', 'colour_key' => '', 'size_key' => '', 'label' => 'Default', 'sku' => 'WT-'.strtoupper($slug), 'price' => '']], 'default_variant_key' => 'none--none', 'status' => 'active',
        ])->assertRedirect();

        return Product::query()->where('slug', $slug)->sole();
    }

    private function image(string $name): MediaAsset
    {
        $id = (string) Str::ulid();

        return MediaAsset::query()->create(['id' => $id, 'provider_asset_id' => 'asset-'.$id, 'provider_public_id' => 'future/'.$id, 'resource_type' => MediaResourceType::Image, 'format' => 'jpg', 'mime_type' => 'image/jpeg', 'original_filename' => $name.'.jpg', 'internal_title' => $name, 'default_alt_text' => $name, 'accessibility_classification' => AccessibilityClassification::Informative, 'is_decorative' => false, 'state' => MediaAssetState::Ready, 'bytes' => 1000, 'uploaded_by' => $this->manager->id, 'confirmed_at' => now()]);
    }
}
