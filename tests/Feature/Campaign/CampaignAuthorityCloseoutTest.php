<?php

namespace Tests\Feature\Campaign;

use App\Domain\Campaign\Actions\ApproveCampaignClaim;
use App\Domain\Campaign\Actions\ArchiveCampaignProduct;
use App\Domain\Campaign\Actions\AssignCampaignMedia;
use App\Domain\Campaign\Actions\AssignCampaignProduct;
use App\Domain\Campaign\Actions\CreateCampaign;
use App\Domain\Campaign\Actions\CreateCampaignClaim;
use App\Domain\Campaign\Actions\RemoveCampaignMedia;
use App\Domain\Campaign\Actions\RestoreCampaignProduct;
use App\Domain\Campaign\Actions\SubmitCampaignClaim;
use App\Domain\Campaign\Actions\UpdateCampaignClaim;
use App\Domain\Campaign\Actions\UpdateCampaignMediaUsage;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignClaim;
use App\Domain\Campaign\Support\CampaignStateFingerprint;
use App\Domain\Catalogue\Actions\CreateProduct;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\PermissionMetadata;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class CampaignAuthorityCloseoutTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
        $this->author = User::factory()->create();
    }

    public function test_permissions_and_unassigned_role_are_exact_sensitive_and_idempotent(): void
    {
        $permissions = [PermissionRegistry::CAMPAIGN_CLAIMS_REVIEW, PermissionRegistry::CAMPAIGN_CLAIMS_APPROVE, PermissionRegistry::CAMPAIGN_CLAIMS_REJECT, PermissionRegistry::CAMPAIGN_CLAIMS_WITHDRAW_APPROVAL];
        foreach ($permissions as $permission) {
            $this->assertTrue(PermissionRegistry::contains($permission));
            $this->assertTrue(PermissionMetadata::isSensitive($permission));
            $this->assertNotEmpty(PermissionMetadata::for($permission)['description']);
        }
        $role = Role::findByName(RoleRegistry::CAMPAIGN_CLAIMS_APPROVER);
        $this->assertEqualsCanonicalizing($permissions, $role->permissions->pluck('name')->all());
        $this->assertDatabaseMissing('model_has_roles', ['role_id' => $role->id]);
        $this->assertNotContains(PermissionRegistry::CAMPAIGN_CLAIMS_APPROVE, RoleRegistry::permissionBundles()[RoleRegistry::CMS_MANAGER]);
        app(ProvisionRegisteredAccess::class)->handle();
        $this->assertSame(1, Role::query()->where('name', RoleRegistry::CAMPAIGN_CLAIMS_APPROVER)->count());
    }

    public function test_approval_requires_permission_and_allows_the_submitter_to_approve(): void
    {
        [$campaign, $claim] = $this->submittedClaim();
        try {
            app(ApproveCampaignClaim::class)->handle($this->author, $campaign, $claim, $this->claims($campaign));
            $this->fail('Unauthorized approval succeeded.');
        } catch (AuthorizationException) {
            $this->assertSame('in_review', $claim->fresh()->approval_status);
        }
        $this->author->givePermissionTo(PermissionRegistry::CAMPAIGN_CLAIMS_APPROVE);
        $approved = app(ApproveCampaignClaim::class)->handle($this->author, $campaign, $claim, $this->claims($campaign));
        $this->assertSame('approved', $approved->approval_status);
        $this->assertSame($approved->value_checksum, $approved->approved_checksum);
        $this->assertSame($this->author->id, $approved->approved_by);
        $this->assertDatabaseHas('audit_records', [
            'action' => 'campaign.claim.approved',
            'actor_user_id' => $this->author->id,
        ]);
    }

    public function test_separate_authorized_actor_approves_and_material_edit_invalidates(): void
    {
        [$campaign, $claim] = $this->submittedClaim();
        $approver = User::factory()->create();
        $approver->givePermissionTo(PermissionRegistry::CAMPAIGN_CLAIMS_APPROVE);
        $approved = app(ApproveCampaignClaim::class)->handle($approver, $campaign, $claim, $this->claims($campaign));
        $this->assertSame($approved->value_checksum, $approved->approved_checksum);
        $this->assertSame($approver->id, $approved->approved_by);
        $updated = app(UpdateCampaignClaim::class)->handle($this->author, $campaign, $approved, $this->claims($campaign), 'Changed window.', 'policy:changed', 'Changed evidence');
        $this->assertSame('draft', $updated->approval_status);
        $this->assertNull($updated->approved_checksum);
        $this->assertDatabaseHas('audit_records', ['action' => 'campaign.claim.approval-invalidated']);
    }

    public function test_target_archive_restore_compacts_order_and_preserves_products(): void
    {
        $campaign = $this->campaign();
        $one = $this->product();
        $two = $this->product();
        $first = app(AssignCampaignProduct::class)->handle($this->author, $campaign, $one, $this->targets($campaign));
        $second = app(AssignCampaignProduct::class)->handle($this->author, $campaign, $two, $this->targets($campaign));
        app(ArchiveCampaignProduct::class)->handle($this->author, $campaign, $first, $this->targets($campaign), 'Campaign edit');
        $this->assertSame(0, $second->fresh()->position);
        $this->assertNull($one->fresh()->archived_at);
        app(RestoreCampaignProduct::class)->handle($this->author, $campaign, $first->fresh(), $this->targets($campaign));
        $this->assertSame(1, $first->fresh()->position);
    }

    public function test_media_update_and_removal_preserve_asset_truth(): void
    {
        $campaign = $this->campaign();
        $asset = $this->image();
        $usage = app(AssignCampaignMedia::class)->handle($this->author, $campaign, $asset, $this->media($campaign));
        $updated = app(UpdateCampaignMediaUsage::class)->handle($this->author, $campaign, $usage, $this->media($campaign), 'Contextual Campaign card');
        $this->assertSame('Contextual Campaign card', $updated->alt_text_override);
        app(RemoveCampaignMedia::class)->handle($this->author, $campaign, $updated, $this->media($campaign));
        $this->assertDatabaseHas('media_assets', ['id' => $asset->id, 'provider_asset_id' => $asset->provider_asset_id]);
        $this->assertDatabaseMissing('media_usages', ['id' => $usage->id]);
    }

    /** @return array{Campaign, CampaignClaim} */
    private function submittedClaim(): array
    {
        $campaign = $this->campaign();
        $claim = app(CreateCampaignClaim::class)->handle($this->author, $campaign, $this->claims($campaign), 'public_window_statement', 'Published Campaign window.', 'policy:window', 'Approved policy');
        app(SubmitCampaignClaim::class)->handle($this->author, $campaign, $claim, $this->claims($campaign));

        return [$campaign, $claim->fresh()];
    }

    private function campaign(): Campaign
    {
        return app(CreateCampaign::class)->handle($this->author, 'pre_order', 'campaign-'.Str::lower(Str::random(8)), ['headline' => 'Pre-Order', 'summary' => 'Campaign summary.', 'cta_label' => 'Explore']);
    }

    private function product(): Product
    {
        $key = Str::lower(Str::random(8));

        return app(CreateProduct::class)->handle($this->author, 'p-'.$key, 'p-'.$key);
    }

    private function image(): MediaAsset
    {
        $id = (string) Str::ulid();

        return MediaAsset::query()->create(['id' => $id, 'provider_asset_id' => 'asset-'.$id, 'provider_public_id' => 'campaign/'.$id, 'resource_type' => MediaResourceType::Image, 'format' => 'jpg', 'mime_type' => 'image/jpeg', 'original_filename' => 'campaign.jpg', 'internal_title' => 'Campaign card', 'default_alt_text' => 'Campaign card', 'accessibility_classification' => AccessibilityClassification::Informative, 'is_decorative' => false, 'state' => MediaAssetState::Ready, 'bytes' => 1000, 'uploaded_by' => $this->author->id, 'confirmed_at' => now()]);
    }

    private function claims(Campaign $campaign): string
    {
        return app(CampaignStateFingerprint::class)->claims($campaign->id);
    }

    private function targets(Campaign $campaign): string
    {
        return app(CampaignStateFingerprint::class)->targets($campaign->id);
    }

    private function media(Campaign $campaign): string
    {
        return app(CampaignStateFingerprint::class)->media($campaign->id);
    }
}
