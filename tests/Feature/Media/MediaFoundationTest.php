<?php

namespace Tests\Feature\Media;

use App\Domain\Audit\Models\AuditRecord;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Media\Actions\ArchiveMediaAsset;
use App\Domain\Media\Actions\AttachMediaUsage;
use App\Domain\Media\Actions\ConfirmUploadedAsset;
use App\Domain\Media\Actions\RestoreMediaAsset;
use App\Domain\Media\Actions\UpdateMediaMetadata;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Support\TransformationProfiles;
use App\Livewire\Admin\Media\MediaLibrary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Fakes\FakeMediaProvider;
use Tests\TestCase;

class MediaFoundationTest extends TestCase
{
    use RefreshDatabase;

    private FakeMediaProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new FakeMediaProvider;
        $this->app->instance(MediaProvider::class, $this->provider);
        app(ProvisionRegisteredAccess::class)->handle();
    }

    public function test_exact_permissions_and_cms_bundle_are_registered(): void
    {
        $expected = ['media.view', 'media.upload', 'media.edit', 'media.replace', 'media.archive', 'media.restore'];
        $this->assertSame($expected, array_values(array_filter(PermissionRegistry::all(), fn (string $p) => str_starts_with($p, 'media.'))));
        foreach ($expected as $permission) {
            $this->assertContains($permission, RoleRegistry::permissionBundles()[RoleRegistry::CMS_MANAGER]);
        }
    }

    public function test_media_routes_are_permission_aware_and_navigation_is_visible(): void
    {
        $ordinary = User::factory()->create();
        $cms = User::factory()->create();
        $this->grantRole($cms, RoleRegistry::CMS_MANAGER);
        $this->actingAs($ordinary)->get(route('admin.media.index'))->assertForbidden();
        $this->actingAs($cms)->get(route('admin.media.index'))->assertOk()->assertSee('Media library');
        $this->actingAs($cms)->get(route('admin.dashboard'))->assertSee('Media library');
    }

    public function test_verified_confirmation_creates_ulid_asset_and_immutable_version_once(): void
    {
        $cms = $this->cms();
        $result = $this->providerResult();
        $asset = app(ConfirmUploadedAsset::class)->handle($cms, $result, 'Editorial portrait', 'A tailored navy suit');
        $again = app(ConfirmUploadedAsset::class)->handle($cms, $result, 'Ignored duplicate', 'Ignored');
        $this->assertSame($asset->id, $again->id);
        $this->assertSame(26, strlen($asset->id));
        $this->assertSame(1, $asset->versions()->count());
        $this->assertDatabaseHas('audit_records', ['action' => 'media.asset.confirmed']);
        $this->assertSame(1, AuditRecord::query()->where('action', 'media.asset.confirmed')->count());
    }

    public function test_metadata_is_validated_audited_and_provider_facts_are_unchanged(): void
    {
        $cms = $this->cms();
        $asset = app(ConfirmUploadedAsset::class)->handle($cms, $this->providerResult(), 'Original', 'Original alt');
        app(UpdateMediaMetadata::class)->handle($cms, $asset, ['internal_title' => 'Updated', 'default_alt_text' => 'Updated alt', 'accessibility_classification' => 'informative', 'focal_x' => .25, 'focal_y' => .75, 'provider_asset_id' => 'tampered']);
        $fresh = $asset->fresh();
        $this->assertSame('Updated', $fresh->internal_title);
        $this->assertSame('asset-1', $fresh->provider_asset_id);
        $this->assertDatabaseHas('audit_records', ['action' => 'media.asset.metadata-updated']);
    }

    public function test_usage_archive_and_restore_preserve_references(): void
    {
        $cms = $this->cms();
        $asset = app(ConfirmUploadedAsset::class)->handle($cms, $this->providerResult(), 'Reusable', 'Useful alt');
        app(AttachMediaUsage::class)->handle($cms, $asset, 'test-owner', '01TEST', 'hero', 'Context alt');
        app(ArchiveMediaAsset::class)->handle($cms, $asset, 'Campaign ended');
        $this->assertSame(MediaAssetState::Archived, $asset->fresh()->state);
        $this->assertSame(1, $asset->usages()->count());
        $this->expectException(\RuntimeException::class);
        app(AttachMediaUsage::class)->handle($cms, $asset->fresh(), 'test-owner', '02TEST', 'hero');
    }

    public function test_restore_fails_closed_then_succeeds_after_provider_verification(): void
    {
        $cms = $this->cms();
        $asset = app(ConfirmUploadedAsset::class)->handle($cms, $this->providerResult(), 'Restore', 'Alt');
        app(ArchiveMediaAsset::class)->handle($cms, $asset, 'Temporary');
        $this->provider->exists = false;
        try {
            app(RestoreMediaAsset::class)->handle($cms, $asset->fresh());
            $this->fail('Restore unexpectedly succeeded.');
        } catch (\RuntimeException) {
            $this->assertSame(MediaAssetState::Archived, $asset->fresh()->state);
        }
        $this->provider->exists = true;
        app(RestoreMediaAsset::class)->handle($cms, $asset->fresh());
        $this->assertSame(MediaAssetState::Ready, $asset->fresh()->state);
    }

    public function test_profiles_and_private_delivery_are_deterministic(): void
    {
        $this->assertSame(app(TransformationProfiles::class)->get('product_card'), app(TransformationProfiles::class)->get('product_card'));
        $url = $this->provider->privateDeliveryUrl('private-id', 'image', 'admin_preview', 12345);
        $this->assertStringContainsString('expires=12345', $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_library_search_filters_and_query_budget(): void
    {
        $cms = $this->cms();
        app(ConfirmUploadedAsset::class)->handle($cms, $this->providerResult(), 'Navy portrait', 'Alt');
        $this->actingAs($cms);
        Livewire::test(MediaLibrary::class)->set('search', 'Navy')->assertSee('Navy portrait')->set('type', 'video')->assertDontSee('Navy portrait')->set('sort', 'unsafe')->assertOk();
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $this->get(route('admin.media.index'))->assertOk();
        $this->assertLessThanOrEqual(15, $queries);
    }

    /** @return array<string,mixed> */
    private function providerResult(): array
    {
        return ['asset_id' => 'asset-1', 'public_id' => 'testing/media/01TEST', 'version' => 1, 'resource_type' => 'image', 'format' => 'jpg', 'mime_type' => 'image/jpeg', 'original_filename' => 'portrait.jpg', 'width' => 1200, 'height' => 1500, 'bytes' => 500000, 'checksum' => 'abc'];
    }

    private function cms(): User
    {
        $u = User::factory()->create();
        $this->grantRole($u, RoleRegistry::CMS_MANAGER);

        return $u;
    }

    private function grantRole(User $user, string $role): void
    {
        app(ControlledRoleMutation::class)->run(fn () => $user->assignRole($role));
    }
}
