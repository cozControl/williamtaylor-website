<?php

namespace Tests\Feature\Campaign;

use App\Domain\Campaign\Actions\ApproveCampaignClaim;
use App\Domain\Campaign\Actions\AssignCampaignMedia;
use App\Domain\Campaign\Actions\AssignCampaignProduct;
use App\Domain\Campaign\Actions\CreateCampaign;
use App\Domain\Campaign\Actions\CreateCampaignClaim;
use App\Domain\Campaign\Actions\ReviseCampaign;
use App\Domain\Campaign\Actions\ScheduleCampaign;
use App\Domain\Campaign\Actions\SubmitCampaignClaim;
use App\Domain\Campaign\Actions\UpdateCampaignClaim;
use App\Domain\Campaign\Exceptions\StaleCampaignState;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Support\CampaignClaimRegistry;
use App\Domain\Campaign\Support\CampaignEffectiveStateEvaluator;
use App\Domain\Campaign\Support\CampaignReadinessEvaluator;
use App\Domain\Campaign\Support\CampaignStateFingerprint;
use App\Domain\Campaign\Support\CampaignTypeRegistry;
use App\Domain\Catalogue\Actions\CreateProduct;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

final class CampaignFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create();
    }

    public function test_type_and_claim_registries_are_exact_and_fail_closed(): void
    {
        $types = app(CampaignTypeRegistry::class);
        $this->assertSame('pre-order', $types->get('pre_order')['badge_key']);
        $this->assertSame('limited', $types->get('limited_edition')['badge_key']);
        $this->assertSame(['product'], $types->get('pre_order')['target_types']);
        foreach (['sale', 'sold_out', 'new', 'inventory'] as $bad) {
            try {
                $types->get($bad);
                $this->fail('Unsupported type accepted.');
            } catch (InvalidArgumentException) {
            }
        }
        $this->assertTrue(app(CampaignClaimRegistry::class)->get('pre_order', 'public_window_statement')['legal_approval_required']);
        $this->expectException(InvalidArgumentException::class);
        app(CampaignClaimRegistry::class)->get('limited_edition', 'stock_quantity');
    }

    public function test_creation_and_revision_are_immutable_stale_safe_and_empty(): void
    {
        $c = $this->campaign();
        $this->assertSame(1, $c->currentDraftRevision->revision_number);
        $this->assertDatabaseCount('campaign_products', 0);
        $this->assertDatabaseCount('campaign_claims', 0);
        $expected = $this->identity($c);
        $r = app(ReviseCampaign::class)->handle($this->actor, $c, $expected, ['headline' => 'New headline', 'summary' => 'New summary', 'cta_label' => 'Explore']);
        $this->assertSame(2, $r->revision_number);
        try {
            app(ReviseCampaign::class)->handle($this->actor, $c, $expected, ['headline' => 'Third', 'summary' => 'Third summary', 'cta_label' => 'Explore']);
            $this->fail('Stale revision accepted.');
        } catch (StaleCampaignState) {
            $this->assertDatabaseCount('campaign_revisions', 2);
        }
        $this->expectException(LogicException::class);
        $r->update(['headline' => 'mutated']);
    }

    public function test_product_target_is_ordered_and_does_not_mutate_product(): void
    {
        $c = $this->campaign();
        $p = $this->product();
        $before = $p->updated_at;
        app(AssignCampaignProduct::class)->handle($this->actor, $c, $p, $this->targets($c));
        $this->assertDatabaseHas('campaign_products', ['campaign_id' => $c->id, 'product_id' => $p->id, 'position' => 0]);
        $this->assertEquals($before, $p->fresh()->updated_at);
        $this->expectException(InvalidArgumentException::class);
        app(AssignCampaignProduct::class)->handle($this->actor, $c, $p, $this->targets($c));
    }

    public function test_claim_lifecycle_requires_evidence_invalidates_approval_and_legal_approval_is_blocked(): void
    {
        $c = $this->campaign();
        $claim = app(CreateCampaignClaim::class)->handle($this->actor, $c, $this->claims($c), 'public_window_statement', 'Available during the published campaign window.', 'policy:campaign-window', 'Approved scheduling policy');
        app(SubmitCampaignClaim::class)->handle($this->actor, $c, $claim, $this->claims($c));
        $this->assertSame('in_review', $claim->fresh()->approval_status);
        $this->expectException(AuthorizationException::class);
        app(ApproveCampaignClaim::class)->handle($this->actor, $c, $claim->fresh(), $this->claims($c));
    }

    public function test_material_claim_update_removes_existing_approval(): void
    {
        $c = $this->campaign();
        $claim = app(CreateCampaignClaim::class)->handle($this->actor, $c, $this->claims($c), 'public_window_statement', 'Initial period.', 'policy:one', 'Evidence one');
        $claim->forceFill(['approval_status' => 'approved', 'approved_by' => $this->actor->id, 'approved_at' => now()])->save();
        $updated = app(UpdateCampaignClaim::class)->handle($this->actor, $c, $claim, $this->claims($c), 'Changed period.', 'policy:two', 'Evidence two');
        $this->assertSame('draft', $updated->approval_status);
        $this->assertNull($updated->approved_by);
    }

    public function test_schedule_is_timezone_safe_utc_and_effective_boundaries_are_derived_without_scheduler(): void
    {
        $c = $this->campaign();
        app(ScheduleCampaign::class)->handle($this->actor, $c, $this->identity($c), '2026-08-01 09:00', '2026-08-02 09:00');
        $c = $c->fresh();
        $this->assertSame('2026-08-01 06:00:00', $c->starts_at->format('Y-m-d H:i:s'));
        $this->assertContains('missing_approved_revision', app(CampaignReadinessEvaluator::class)->evaluate($c)->failureCodes);
        $this->assertSame('not_ready', app(CampaignEffectiveStateEvaluator::class)->evaluate($c, CarbonImmutable::parse('2026-08-01T06:00:00Z')));
        $this->expectException(InvalidArgumentException::class);
        app(ScheduleCampaign::class)->handle($this->actor, $c, $this->identity($c), '2026-08-02 09:00', '2026-08-01 09:00');
    }

    public function test_media_role_is_campaign_owned_meaningful_and_singular(): void
    {
        $c = $this->campaign();
        $asset = $this->image('Campaign card');
        $usage = app(AssignCampaignMedia::class)->handle($this->actor, $c, $asset, $this->media($c));
        $this->assertSame(Campaign::class, $usage->owner_type);
        $this->expectException(InvalidArgumentException::class);
        app(AssignCampaignMedia::class)->handle($this->actor, $c, $this->image('Second'), $this->media($c));
    }

    private function campaign(): Campaign
    {
        return app(CreateCampaign::class)->handle($this->actor, 'pre_order', 'campaign-'.Str::lower(Str::random(8)), ['headline' => 'Pre-Order', 'summary' => 'Reserve future pieces.', 'cta_label' => 'Explore']);
    }

    private function product(): Product
    {
        return app(CreateProduct::class)->handle($this->actor, 'p-'.Str::lower(Str::random(8)), 'p-'.Str::lower(Str::random(8)));
    }

    private function identity(Campaign $c): string
    {
        return app(CampaignStateFingerprint::class)->identity($c->fresh());
    }

    private function targets(Campaign $c): string
    {
        return app(CampaignStateFingerprint::class)->targets($c->id);
    }

    private function claims(Campaign $c): string
    {
        return app(CampaignStateFingerprint::class)->claims($c->id);
    }

    private function media(Campaign $c): string
    {
        return app(CampaignStateFingerprint::class)->media($c->id);
    }

    private function image(string $alt): MediaAsset
    {
        $id = (string) Str::ulid();

        return MediaAsset::query()->create(['id' => $id, 'provider_asset_id' => 'asset-'.$id, 'provider_public_id' => 'campaign/'.$id, 'resource_type' => MediaResourceType::Image, 'format' => 'jpg', 'mime_type' => 'image/jpeg', 'original_filename' => 'campaign.jpg', 'internal_title' => 'Campaign image', 'default_alt_text' => $alt, 'accessibility_classification' => AccessibilityClassification::Informative, 'is_decorative' => false, 'state' => MediaAssetState::Ready, 'bytes' => 1000, 'uploaded_by' => $this->actor->id, 'confirmed_at' => now()]);
    }
}
