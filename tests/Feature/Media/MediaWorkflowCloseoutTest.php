<?php

namespace Tests\Feature\Media;

use App\Domain\Audit\Models\AuditRecord;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Media\Actions\AttachMediaUsage;
use App\Domain\Media\Actions\ConfirmUploadedAsset;
use App\Domain\Media\Actions\CreateUploadIntent;
use App\Domain\Media\Actions\ReplaceMediaAsset;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Exceptions\ExactDuplicateMediaException;
use App\Domain\Media\Jobs\ReconcileMediaAsset;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUploadIntent;
use App\Domain\Media\Support\MediaFilePolicy;
use App\Domain\Media\Support\ReplacementProposalStore;
use App\Infrastructure\Media\Testing\DeterministicMediaProvider;
use App\Livewire\Admin\Media\MediaDetail;
use App\Livewire\Admin\Media\MediaLibrary;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\Fakes\FakeMediaProvider;
use Tests\TestCase;

class MediaWorkflowCloseoutTest extends TestCase
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

    public function test_upload_intent_requires_permission_and_enforces_type_and_size_policy(): void
    {
        $ordinary = User::factory()->create();
        try {
            app(CreateUploadIntent::class)->handle($ordinary, 'image', 'image/jpeg', 1000);
            $this->fail('Unauthorized intent unexpectedly succeeded.');
        } catch (AuthorizationException) {
            $this->assertSame(0, $this->provider->intentCount);
        }

        $cms = $this->cms();
        $intent = app(CreateUploadIntent::class)->handle($cms, 'image', 'image/jpeg', 1000);
        $this->assertLessThanOrEqual(300, $intent->expiresAt - now()->timestamp);
        $this->assertGreaterThan(0, $intent->expiresAt - now()->timestamp);
        $this->assertStringStartsWith(config('media.cloudinary.folder').'/', $intent->parameters['public_id']);
        $this->assertArrayNotHasKey('api_secret', $intent->parameters);
        $this->assertSame(1, $this->provider->intentCount);

        foreach ([['image', 'image/svg+xml', 1000], ['video', 'video/quicktime', 1000], ['image', 'image/jpeg', MediaFilePolicy::IMAGE_MAX_BYTES + 1]] as $invalid) {
            try {
                app(CreateUploadIntent::class)->handle($cms, ...$invalid);
                $this->fail('Invalid intent unexpectedly succeeded.');
            } catch (\InvalidArgumentException) {
                $this->assertSame(1, $this->provider->intentCount);
            }
        }
    }

    public function test_deterministic_provider_rejects_stale_or_tampered_evidence(): void
    {
        config(['media.provider' => 'deterministic', 'media.cloudinary.folder' => 'testing/media']);
        $provider = app(DeterministicMediaProvider::class);
        $intent = $provider->createUploadIntent(['public_id' => 'testing/media/test', 'resource_type' => 'image', 'intent_reference' => (string) str()->ulid()]);
        $valid = [...$intent->parameters, 'asset_id' => 'evidence-1', 'format' => 'jpg', 'mime_type' => 'image/jpeg', 'original_filename' => 'test.jpg', 'bytes' => 1000];
        $this->assertSame('evidence-1', $provider->verifyUploadResult($valid)->assetId);

        foreach ([[...$valid, 'evidence_token' => 'tampered'], [...$valid, 'expires_at' => now()->subSecond()->timestamp]] as $invalid) {
            $this->expectProviderFailure(fn () => $provider->verifyUploadResult($invalid));
        }
    }

    public function test_exact_duplicate_requires_reuse_or_reasoned_override(): void
    {
        $cms = $this->cms();
        $existing = $this->confirm($cms, $this->providerResult('asset-1', 'same-checksum'));
        try {
            $this->confirm($cms, $this->providerResult('asset-2', 'same-checksum'));
            $this->fail('Duplicate unexpectedly created.');
        } catch (ExactDuplicateMediaException $exception) {
            $this->assertTrue($existing->is($exception->candidate));
        }
        $this->assertDatabaseCount('media_assets', 1);

        try {
            $this->confirm($cms, $this->providerResult('asset-2', 'same-checksum'), true, '');
            $this->fail('Reasonless override unexpectedly succeeded.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('media_assets', 1);
        }

        $separate = $this->confirm($cms, $this->providerResult('asset-2', 'same-checksum'), true, 'Separate campaign lifecycle');
        $this->assertNotSame($existing->id, $separate->id);
        $this->assertDatabaseHas('audit_records', ['action' => 'media.asset.duplicate-override', 'reason' => 'Separate campaign lifecycle']);
    }

    public function test_livewire_duplicate_reuse_creates_no_asset_or_audit_noise(): void
    {
        $cms = $this->cms();
        $existing = $this->confirm($cms, $this->providerResult('asset-1', 'same-checksum'));
        $auditCount = AuditRecord::query()->count();
        $intent = app(CreateUploadIntent::class)->handle($cms, 'image', 'image/jpeg', 500000);
        $duplicateResult = $this->providerResult('asset-2', 'same-checksum');
        $duplicateResult['public_id'] = $intent->parameters['public_id'];
        $component = Livewire::actingAs($cms)->test(MediaLibrary::class)
            ->call('confirmUpload', [
                'intentReference' => $intent->reference,
                'providerEvidence' => $duplicateResult,
                'title' => 'Duplicate selection',
                'altText' => null,
                'overrideDuplicate' => false,
                'overrideReason' => null,
            ])
            ->assertReturned(fn (array $result): bool => $result['status'] === 'duplicate_detected' && $result['candidate']['id'] === $existing->id);
        $component->call('reuseDuplicate', $existing->id, $intent->reference)
            ->assertReturned(fn (array $result): bool => $result['assetId'] === $existing->id);
        $this->assertNotNull(MediaUploadIntent::query()->findOrFail($intent->reference)->consumed_at);
        $this->assertDatabaseCount('media_assets', 1);
        $this->assertSame($auditCount, AuditRecord::query()->count());
    }

    public function test_read_only_media_user_sees_no_upload_or_replacement_controls(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo([PermissionRegistry::ADMIN_ACCESS, PermissionRegistry::MEDIA_VIEW]);
        $owner = $this->cms();
        $asset = $this->confirm($owner, $this->providerResult('asset-1', 'one'));
        $this->actingAs($viewer)->get(route('admin.media.index'))->assertOk()->assertDontSee('data-media-upload-queue', false);
        $this->actingAs($viewer)->get(route('admin.media.show', $asset))->assertOk()->assertDontSee('data-media-replacement', false);
    }

    public function test_valid_replacement_preserves_usage_and_history_and_audits_once(): void
    {
        $cms = $this->cms();
        $asset = $this->confirm($cms, $this->providerResult('asset-1', 'one'));
        app(AttachMediaUsage::class)->handle($cms, $asset, 'fixture', 'owner-1', 'hero');
        $verified = $this->provider->verifyUploadResult($this->providerResult('asset-2', 'two', 1800, 1200));
        $proposal = app(ReplacementProposalStore::class)->put($cms, $asset, $verified);
        app(ReplaceMediaAsset::class)->handleProposal($cms, $asset, $proposal->token, 'New campaign master');

        $this->assertSame('asset-2', $asset->fresh()->provider_asset_id);
        $this->assertSame(2, $asset->versions()->count());
        $this->assertSame(1, $asset->versions()->where('is_current', true)->count());
        $this->assertSame(1, $asset->usages()->count());
        $this->assertSame(1, AuditRecord::query()->where('action', 'media.asset.replaced')->count());
        $this->assertNotEmpty($proposal->warnings);
    }

    public function test_replacement_fingerprint_detects_version_or_usage_change(): void
    {
        $cms = $this->cms();
        $asset = $this->confirm($cms, $this->providerResult('asset-1', 'one'));
        $proposal = app(ReplacementProposalStore::class)->put($cms, $asset, $this->provider->verifyUploadResult($this->providerResult('asset-2', 'two')));
        app(AttachMediaUsage::class)->handle($cms, $asset, 'fixture', 'owner-1', 'hero');

        $this->expectException(DomainException::class);
        app(ReplaceMediaAsset::class)->handleProposal($cms, $asset, $proposal->token, 'Stale proposal');
    }

    public function test_failed_replacement_audit_rolls_back_current_version(): void
    {
        $cms = $this->cms();
        $asset = $this->confirm($cms, $this->providerResult('asset-1', 'one'));
        $proposal = app(ReplacementProposalStore::class)->put($cms, $asset, $this->provider->verifyUploadResult($this->providerResult('asset-2', 'two')));
        AuditRecord::creating(function (AuditRecord $record): void {
            if ($record->action === 'media.asset.replaced') {
                throw new RuntimeException('Audit unavailable');
            }
        });
        try {
            app(ReplaceMediaAsset::class)->handleProposal($cms, $asset, $proposal->token, 'Must roll back');
            $this->fail('Replacement unexpectedly committed.');
        } catch (RuntimeException) {
            $this->assertSame('asset-1', $asset->fresh()->provider_asset_id);
            $this->assertSame(1, $asset->versions()->count());
            $this->assertTrue((bool) $asset->versions()->sole()->is_current);
        }
    }

    public function test_livewire_replacement_refreshes_stale_comparison_and_resets_confirmation(): void
    {
        $cms = $this->cms();
        $asset = $this->confirm($cms, $this->providerResult('asset-1', 'one'));
        $component = Livewire::actingAs($cms)->test(MediaDetail::class, ['assetId' => $asset->id])
            ->call('prepareReplacement', $this->providerResult('asset-2', 'two'))
            ->set('reason', 'Preserved reason')
            ->set('replacementConfirmed', true);
        app(AttachMediaUsage::class)->handle($cms, $asset, 'fixture', 'owner-1', 'hero');
        $component->call('applyReplacement')
            ->assertSet('replacementConfirmed', false)
            ->assertSet('reason', 'Preserved reason')
            ->assertSee('state changed')
            ->assertDispatched('media-replacement-stale');
        $this->assertSame('asset-1', $asset->fresh()->provider_asset_id);
    }

    public function test_reconciliation_is_idempotent_and_exposes_provider_failure(): void
    {
        $cms = $this->cms();
        $asset = $this->confirm($cms, $this->providerResult('asset-1', 'one'));
        $this->provider->exists = false;
        $job = new ReconcileMediaAsset($asset->id);
        $job->handle($this->provider);
        $job->handle($this->provider);
        $this->assertSame(MediaAssetState::Failed, $asset->fresh()->state);
        $this->assertSame('Provider asset could not be verified.', $asset->fresh()->processing_error);
        $this->assertSame(3, $job->tries);
    }

    private function expectProviderFailure(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Invalid provider evidence unexpectedly succeeded.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('invalid or stale', $exception->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function providerResult(string $assetId, string $checksum, int $width = 1200, int $height = 1500): array
    {
        return ['asset_id' => $assetId, 'public_id' => 'testing/media/'.$assetId, 'version' => 1, 'resource_type' => 'image', 'format' => 'jpg', 'mime_type' => 'image/jpeg', 'original_filename' => $assetId.'.jpg', 'width' => $width, 'height' => $height, 'bytes' => 500000, 'checksum' => $checksum];
    }

    private function confirm(User $actor, array $result, bool $override = false, ?string $reason = null): MediaAsset
    {
        return app(ConfirmUploadedAsset::class)->handle($actor, $result, 'Media '.$result['asset_id'], 'Meaningful alt text', $override, $reason);
    }

    private function cms(): User
    {
        $user = User::factory()->create();
        app(ControlledRoleMutation::class)->run(fn () => $user->assignRole(RoleRegistry::CMS_MANAGER));

        return $user;
    }
}
