<?php

namespace Tests\Feature\Homepage;

use App\Domain\Campaign\Actions\CreateCampaign;
use App\Domain\Campaign\Actions\ReviseCampaign;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Support\CampaignStateFingerprint;
use App\Domain\Catalogue\Actions\CreateProduct;
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
use Tests\TestCase;

final class HomepageLimitedEditionManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $approver;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
        $this->manager = User::factory()->create(['email_verified_at' => now()]);
        $this->approver = User::factory()->create(['email_verified_at' => now()]);
        app(ControlledRoleMutation::class)->run(fn () => $this->manager->assignRole(RoleRegistry::CMS_MANAGER));
        app(ControlledRoleMutation::class)->run(fn () => $this->approver->assignRole(RoleRegistry::SUPER_ADMINISTRATOR));
        $this->category = ProductCategory::query()->create(['name' => 'Limited Edition', 'slug' => 'limited-edition-products', 'is_visible' => true, 'position' => 0, 'created_by' => $this->manager->id, 'updated_by' => $this->manager->id]);
    }

    public function test_campaign_workspace_distinguishes_types_and_enforces_type_specific_fields(): void
    {
        $this->get(route('admin.campaigns.index'))->assertRedirect(route('login'));
        $ordinary = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($ordinary)->get(route('admin.campaigns.index'))->assertForbidden();

        $this->actingAs($this->manager)->get(route('admin.campaigns.index'))
            ->assertOk()
            ->assertSeeText('New Campaign')
            ->assertSee(route('admin.campaigns.create'), false);
        $this->actingAs($this->manager)->get(route('admin.campaigns.create'))
            ->assertOk()->assertSeeText('Choose Campaign type')->assertSeeText('Pre-Order')->assertSeeText('Limited Edition');
        $this->actingAs($this->manager)->get(route('admin.campaigns.create', ['type' => 'limited_edition']))
            ->assertOk()->assertSeeText('Fixed edition claim')->assertDontSeeText('Estimated shipping date');
        $this->actingAs($this->manager)->get(route('admin.campaigns.create', ['type' => 'pre_order']))
            ->assertOk()->assertSeeText('Estimated shipping date')->assertDontSeeText('Fixed edition claim');
        $this->actingAs($this->manager)->get(route('admin.homepage.limited-edition.edit'))
            ->assertOk()->assertSeeText('Three supplied positions')->assertSeeText('Position 3')->assertDontSeeText('Position 4');
        $this->get(route('home'))->assertOk()->assertDontSeeText('Remaining');

        foreach (['pre_order' => 'Pre-Order list entry', 'limited_edition' => 'Limited Edition list entry'] as $type => $headline) {
            app(CreateCampaign::class)->handle($this->manager, $type, $type.'-list-entry', [
                'headline' => $headline,
                'summary' => 'Draft Campaign used to verify the shared list.',
                'cta_label' => 'View Piece',
            ]);
        }
        $this->actingAs($this->manager)->get(route('admin.campaigns.index'))
            ->assertOk()->assertSeeText('Pre-Order list entry')->assertSeeText('Pre-Order')
            ->assertSeeText('Limited Edition list entry')->assertSeeText('Limited Edition')
            ->assertSee('data-campaign-index', false)
            ->assertSee('data-campaign-table', false)
            ->assertSee('data-label="Schedule"', false)
            ->assertSee('data-campaign-state', false)
            ->assertSee('.campaign-index-panel{', false);
    }

    public function test_limited_edition_validation_preserves_media_and_rejects_preorder_only_input(): void
    {
        $product = $this->product('Validation Piece', 'validation-piece', '450,000');
        $image = $this->image('Validation campaign image');
        $payload = $this->payload($product, $image, 'validation-edition');
        unset($payload['edition_statement']);
        $payload['estimated_delivery_date'] = now('Africa/Nairobi')->addDays(20)->format('Y-m-d');

        $this->actingAs($this->manager)->post(route('admin.campaigns.store'), $payload)
            ->assertSessionHasErrors(['edition_statement', 'estimated_delivery_date'])
            ->assertSessionHasInput('media_asset_id', $image->id)
            ->assertSessionHasInput('headline', 'Validation Piece Campaign');
        $this->assertDatabaseCount('campaigns', 0);
    }

    public function test_incomplete_product_cannot_make_limited_campaign_public(): void
    {
        $product = app(CreateProduct::class)->handle($this->manager, 'Incomplete Piece', 'incomplete-piece');
        $image = $this->image('Incomplete campaign image');
        $this->actingAs($this->manager)->get(route('admin.campaigns.create', ['type' => 'limited_edition']))
            ->assertOk()->assertSeeText('Needs attention')->assertSeeText('Missing category');
        $this->actingAs($this->manager)->post(route('admin.campaigns.store'), $this->payload($product, $image, 'incomplete-edition'))->assertRedirect();
        $campaign = Campaign::query()->sole();

        $this->actingAs($this->approver)->post(route('admin.campaigns.approve', $campaign))
            ->assertSessionHasErrors('campaign');
        $this->assertSame('draft', $campaign->fresh()->lifecycle_status);
        $this->get(route('limited-edition.index'))->assertOk()->assertDontSeeText('Incomplete Piece Campaign');
    }

    public function test_campaign_edit_preserves_type_and_resubmits_edition_claims_for_approval(): void
    {
        $product = $this->product('Editable Edition', 'editable-edition', '470,000');
        $image = $this->image('Editable Campaign image');
        $this->actingAs($this->manager)->post(route('admin.campaigns.store'), $this->payload($product, $image, 'editable-edition'))->assertRedirect();
        $campaign = Campaign::query()->sole();

        $updated = $this->payload($product, $image, 'editable-edition', 'Only 18 Made');
        $updated['headline'] = 'Edited Limited Edition Campaign';
        $this->actingAs($this->manager)->put(route('admin.campaigns.update', $campaign), $updated)
            ->assertSessionHas('status');

        $this->assertSame('limited_edition', $campaign->fresh()->campaign_type);
        $this->assertDatabaseHas('campaign_claims', [
            'campaign_id' => $campaign->id,
            'claim_key' => 'edition_statement',
            'normalized_value' => 'Only 18 Made',
            'approval_status' => 'in_review',
        ]);

        $forged = $updated;
        $forged['campaign_type'] = 'pre_order';
        unset($forged['edition_statement'], $forged['edition_evidence_reference'], $forged['edition_evidence_summary']);
        $forged['estimated_delivery_date'] = now('Africa/Nairobi')->addDays(20)->format('Y-m-d');
        $this->actingAs($this->manager)->put(route('admin.campaigns.update', $campaign), $forged)
            ->assertSessionHasErrors('campaign_type');
        $this->assertSame('limited_edition', $campaign->fresh()->campaign_type);
    }

    public function test_campaign_media_requires_effective_alt_and_preserves_selection(): void
    {
        $product = $this->product('Accessible Edition', 'accessible-edition', '480,000');
        $image = $this->image('Campaign image without alt', true);
        $payload = $this->payload($product, $image, 'accessible-edition');

        $this->actingAs($this->manager)->post(route('admin.campaigns.store'), $payload)
            ->assertSessionHasErrors('media_asset_id')
            ->assertSessionHasInput('media_asset_id', $image->id)
            ->assertSessionHasInput('headline', 'Accessible Edition Campaign');
        $this->assertDatabaseCount('campaigns', 0);

        $payload['media_alt_override'] = 'A numbered jacket displayed against a neutral backdrop.';
        $this->actingAs($this->manager)->post(route('admin.campaigns.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('campaigns', 1);
    }

    public function test_approved_campaign_projects_in_configured_homepage_order_and_public_page(): void
    {
        $first = $this->publishedCampaign('First Edition', 'first-edition', '790,000', 'Only 30 Made', 3);
        $second = $this->publishedCampaign('Second Edition', 'second-edition', '620,000', 'Only 25 Made', 2);
        $third = $this->publishedCampaign('Third Edition', 'third-edition', '510,000', 'Only 40 Made', 1);

        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk();
        $homepage = HomepageHero::query()->sole();
        $this->actingAs($this->manager)->put(route('admin.homepage.limited-edition.update'), [
            'lock_version' => $homepage->lock_version,
            'limited_edition_managed' => '1',
            'limited_edition_eyebrow' => 'Exclusive',
            'limited_edition_heading' => 'LIMITED EDITION',
            'limited_edition_cta_label' => 'View All Limited Editions',
            'limited_edition_campaign_1_id' => $third->id,
            'limited_edition_campaign_2_id' => $first->id,
            'limited_edition_campaign_3_id' => $second->id,
        ])->assertRedirect(route('admin.homepage.limited-edition.edit'));

        $home = $this->get(route('home'))->assertOk()
            ->assertSee('data-homepage-limited-edition', false)
            ->assertSee('homepage-limited-edition-projection', false)
            ->assertSeeText('Only 30 Made')
            ->assertSeeText('TZS 790,000')
            ->assertSee('First Edition campaign presentation', false)
            ->assertSee(route('limited-edition.index'), false);
        $html = $home->getContent();
        $this->assertLessThan(strpos($html, 'First Edition'), strpos($html, 'Third Edition'));
        $this->assertLessThan(strpos($html, 'Second Edition'), strpos($html, 'First Edition'));
        $this->assertSame(6, substr_count($html, 'data-limited-edition-campaign='));

        $page = $this->get(route('limited-edition.index'))->assertOk()
            ->assertSeeText('Numbered pieces. Exclusive access.')
            ->assertSeeText('First Edition')
            ->assertSeeText('TZS 620,000')
            ->assertSee(route('products.show', 'first-edition'), false)
            ->assertDontSee('/website/js/index-DxdnTNDA.js', false);
        $pageHtml = $page->getContent();
        $this->assertSame(3, substr_count($pageHtml, 'data-limited-edition-campaign='));
        $this->assertLessThan(strpos($pageHtml, 'Second Edition'), strpos($pageHtml, 'First Edition'));
        $this->assertLessThan(strpos($pageHtml, 'Third Edition'), strpos($pageHtml, 'Second Edition'));
    }

    public function test_homepage_supports_zero_and_partial_eligible_campaigns_without_placeholders(): void
    {
        $eligible = $this->publishedCampaign('Eligible Edition', 'eligible-edition', '350,000', 'Only 12 Made');
        $ended = $this->publishedCampaign('Ended Edition', 'ended-edition', '360,000', 'Only 10 Made');
        $ended->forceFill(['starts_at' => now('UTC')->subDays(4), 'ends_at' => now('UTC')->subDay()])->save();
        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk();
        $homepage = HomepageHero::query()->sole();

        $base = [
            'limited_edition_managed' => '1',
            'limited_edition_eyebrow' => 'Exclusive',
            'limited_edition_heading' => 'LIMITED EDITION',
            'limited_edition_cta_label' => 'View All Limited Editions',
        ];
        $this->actingAs($this->manager)->put(route('admin.homepage.limited-edition.update'), [...$base, 'lock_version' => $homepage->lock_version, 'limited_edition_campaign_1_id' => $ended->id])
            ->assertRedirect();
        $this->get(route('home'))->assertOk()->assertSee('data-homepage-limited-edition', false)->assertDontSeeText('Ended Edition');

        $homepage->refresh();
        $this->actingAs($this->manager)->put(route('admin.homepage.limited-edition.update'), [...$base, 'lock_version' => $homepage->lock_version, 'limited_edition_campaign_1_id' => $ended->id, 'limited_edition_campaign_2_id' => $eligible->id])
            ->assertRedirect();
        $html = $this->get(route('home'))->assertOk()->assertSeeText('Eligible Edition')->assertDontSeeText('Ended Edition')->getContent();
        $this->assertSame(2, substr_count($html, 'data-limited-edition-campaign='));
    }

    public function test_unconfigured_homepage_discovers_only_database_limited_editions(): void
    {
        $campaign = $this->publishedCampaign('Database Edition', 'database-edition', '730,000', 'Only 17 Made');
        $response = $this->get(route('home'))->assertOk()->assertSeeText('Database Edition Campaign');
        $this->assertSame([], $response->viewData('homepageFutureStyle')['campaigns']);
        $section = $response->viewData('homepageLimitedEdition');
        $this->assertSame([$campaign->id], array_column($section['campaigns'], 'id'));
        $html = view('frontend.partials.homepage-limited-edition', ['homepageLimitedEdition' => $section])->render();
        $this->assertStringContainsString(e($section['campaigns'][0]['image']), $html);
        $this->assertStringContainsString('TZS 730,000', $html);
        $this->assertStringContainsString('Only 17 Made', $html);
        $this->assertStringNotContainsString('PRE-ORDER', $html);
        $this->get(route('preorders.index'))->assertDontSeeText('Database Edition Campaign');
        app(ReviseCampaign::class)->handle($this->manager, $campaign, app(CampaignStateFingerprint::class)->identity($campaign), [
            'headline' => 'Database Edition Updated', 'summary' => $campaign->approvedRevision->summary, 'cta_label' => $campaign->approvedRevision->cta_label,
        ]);
        $this->actingAs($this->approver)->post(route('admin.campaigns.approve', $campaign))->assertSessionHas('status');
        $this->get(route('home'))->assertSeeText('Database Edition Updated')->assertDontSeeText('Database Edition Campaign');
        $this->get(route('limited-edition.index'))->assertSeeText('Database Edition Updated');
        $campaign->forceFill(['archived_at' => now()])->save();
        $this->assertSame([], $this->get(route('home'))->viewData('homepageLimitedEdition')['campaigns']);
    }

    private function publishedCampaign(string $title, string $slug, string $price, string $edition, int $startOffset = 1): Campaign
    {
        $product = $this->product($title, $slug, $price);
        $image = $this->image($title.' campaign presentation');
        $this->actingAs($this->manager)->post(route('admin.campaigns.store'), $this->payload($product, $image, $slug.'-campaign', $edition, $startOffset))->assertRedirect();
        $campaign = Campaign::query()->where('internal_code', str_replace('-', '_', $slug.'-campaign'))->sole();
        $this->actingAs($this->approver)->post(route('admin.campaigns.approve', $campaign))->assertSessionHas('status');

        return $campaign->fresh();
    }

    /** @return array<string, string> */
    private function payload(Product $product, MediaAsset $image, string $code, string $edition = 'Only 20 Made', int $startOffset = 1): array
    {
        return [
            'campaign_type' => 'limited_edition',
            'internal_code' => $code,
            'headline' => $product->currentDraftRevision?->title.' Campaign',
            'summary' => 'A numbered atelier edition with approved production evidence.',
            'cta_label' => 'View Piece',
            'product_id' => $product->id,
            'media_asset_id' => $image->id,
            'media_alt_override' => '',
            'starts_at' => now('Africa/Nairobi')->subDays($startOffset)->format('Y-m-d\TH:i'),
            'ends_at' => now('Africa/Nairobi')->addDays(10)->format('Y-m-d\TH:i'),
            'public_window_statement' => 'Available during the published Campaign window.',
            'evidence_reference' => 'policy:limited-window-'.$code,
            'evidence_summary' => 'Approved release calendar.',
            'edition_statement' => $edition,
            'edition_evidence_reference' => 'production-plan:'.$code,
            'edition_evidence_summary' => 'Approved total planned production quantity.',
        ];
    }

    private function product(string $title, string $slug, string $price): Product
    {
        $image = $this->image($title.' Product');
        $this->actingAs($this->manager)->post(route('admin.products.store'), [
            'title' => $title,
            'slug' => $slug,
            'short_description' => 'Canonical Product.',
            'currency' => 'TZS',
            'base_price' => $price,
            'primary_category_id' => $this->category->id,
            'primary_media_id' => $image->id,
            'media_alt' => [$image->id => $title],
            'gallery_media_ids' => [],
            'draft_variants' => [['key' => 'none--none', 'colour_key' => '', 'size_key' => '', 'label' => 'Default', 'sku' => 'WT-'.strtoupper($slug), 'price' => '']],
            'default_variant_key' => 'none--none',
            'status' => 'active',
        ])->assertRedirect();

        return Product::query()->where('slug', $slug)->sole();
    }

    private function image(string $name, bool $withoutAlt = false): MediaAsset
    {
        $id = (string) Str::ulid();

        return MediaAsset::query()->create([
            'id' => $id,
            'provider_asset_id' => 'asset-'.$id,
            'provider_public_id' => 'limited/'.$id,
            'resource_type' => MediaResourceType::Image,
            'format' => 'jpg',
            'mime_type' => 'image/jpeg',
            'original_filename' => Str::slug($name).'.jpg',
            'internal_title' => $name,
            'default_alt_text' => $withoutAlt ? null : $name,
            'accessibility_classification' => AccessibilityClassification::Informative,
            'is_decorative' => false,
            'state' => MediaAssetState::Ready,
            'bytes' => 1000,
            'uploaded_by' => $this->manager->id,
            'confirmed_at' => now(),
        ]);
    }
}
