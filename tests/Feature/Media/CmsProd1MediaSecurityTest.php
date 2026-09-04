<?php

namespace Tests\Feature\Media;

use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Media\Actions\ConfirmUploadIntent;
use App\Domain\Media\Actions\CreateUploadIntent;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Exceptions\MediaUploadFailure;
use App\Domain\Media\Models\MediaUploadIntent;
use App\Infrastructure\Media\Cloudinary\CloudinaryMediaProvider;
use App\Livewire\Admin\Media\MediaLibrary;
use App\Models\User;
use Cloudinary\Api\ApiUtils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fakes\FakeMediaProvider;
use Tests\TestCase;

class CmsProd1MediaSecurityTest extends TestCase
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

    public function test_intent_is_actor_provider_purpose_and_file_bound_then_single_use(): void
    {
        $actor = $this->cms();
        $intent = app(CreateUploadIntent::class)->handle($actor, 'image', 'image/jpeg', 1234);
        $result = $this->providerResult((string) $intent->parameters['public_id']);

        $asset = app(ConfirmUploadIntent::class)->handle($actor, (string) $intent->reference, $result, 'Portrait', 'A navy jacket');

        $this->assertSame('ready', $asset->state->value);
        $this->assertNotNull(MediaUploadIntent::query()->findOrFail($intent->reference)->consumed_at);
        $this->expectFailure('media.intent_consumed', fn () => app(ConfirmUploadIntent::class)->handle($actor, (string) $intent->reference, $result, 'Portrait', 'A navy jacket'));
    }

    public function test_cross_actor_stale_and_cross_intent_confirmation_fail_closed(): void
    {
        $owner = $this->cms();
        $other = $this->cms();
        $intent = app(CreateUploadIntent::class)->handle($owner, 'image', 'image/jpeg', 1234);
        $result = $this->providerResult((string) $intent->parameters['public_id']);

        $this->expectFailure('media.intent_invalid', fn () => app(ConfirmUploadIntent::class)->handle($other, (string) $intent->reference, $result, 'Portrait', 'Alt'));

        MediaUploadIntent::query()->whereKey($intent->reference)->update(['expires_at' => now()->subMinute()]);
        $this->expectFailure('media.intent_expired', fn () => app(ConfirmUploadIntent::class)->handle($owner, (string) $intent->reference, $result, 'Portrait', 'Alt'));

        $second = app(CreateUploadIntent::class)->handle($owner, 'image', 'image/jpeg', 1234);
        $this->expectFailure('media.provider_public_id_invalid', fn () => app(ConfirmUploadIntent::class)->handle($owner, (string) $second->reference, $result, 'Portrait', 'Alt'));
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_cloudinary_confirmation_uses_signed_public_id_version_and_iso_created_at(): void
    {
        config(['media.cloudinary' => ['cloud_name' => 'example', 'api_key' => 'key', 'api_secret' => 'secret', 'folder' => 'testing/media']]);
        $result = [
            'asset_id' => 'cloud-asset', 'public_id' => 'testing/media/asset', 'version' => 42,
            'resource_type' => 'image', 'type' => 'upload', 'format' => 'jpg',
            'bytes' => 1234, 'width' => 1200, 'height' => 1500,
            'created_at' => now()->toIso8601String(),
        ];
        $result['signature'] = ApiUtils::signParameters(['public_id' => $result['public_id'], 'version' => (string) $result['version']], 'secret');

        $verified = app(CloudinaryMediaProvider::class)->verifyUploadResult($result);
        $this->assertSame('cloud-asset', $verified->assetId);
        $this->assertSame('image/jpeg', $verified->mimeType);

        $this->expectFailure('media.provider_signature_invalid', fn () => app(CloudinaryMediaProvider::class)->verifyUploadResult([...$result, 'signature' => 'forged']));
        $this->expectFailure('media.provider_timestamp_invalid', fn () => app(CloudinaryMediaProvider::class)->verifyUploadResult([...$result, 'created_at' => now()->subMinutes(6)->toIso8601String()]));
        $this->expectFailure('media.provider_folder_mismatch', function () use ($result): void {
            $wrong = [...$result, 'public_id' => 'other/folder/asset'];
            $wrong['signature'] = ApiUtils::signParameters(['public_id' => $wrong['public_id'], 'version' => (string) $wrong['version']], 'secret');
            app(CloudinaryMediaProvider::class)->verifyUploadResult($wrong);
        });
    }

    public function test_cloudinary_intent_uses_one_canonical_public_id_without_folder_reprefixing(): void
    {
        config(['media.cloudinary' => ['cloud_name' => 'example', 'api_key' => 'key', 'api_secret' => 'secret', 'folder' => 'testing/media']]);
        $reference = (string) str()->ulid();
        $intent = app(CloudinaryMediaProvider::class)->createUploadIntent([
            'public_id' => 'testing/media/2026/09/asset',
            'resource_type' => 'image',
            'intent_reference' => $reference,
        ]);

        $this->assertSame('testing/media/2026/09/asset', $intent->parameters['public_id']);
        $this->assertArrayNotHasKey('folder', $intent->parameters);
        $this->assertSame('intent_reference='.$reference, $intent->parameters['context']);
        $this->assertArrayHasKey('signature', $intent->parameters);
    }

    public function test_livewire_accepts_canonical_serialized_browser_confirmation_payload(): void
    {
        $actor = $this->cms();
        $intent = app(CreateUploadIntent::class)->handle($actor, 'image', 'image/jpeg', 1234);

        Livewire::actingAs($actor)->test(MediaLibrary::class)
            ->call('confirmUpload', [
                'intentReference' => $intent->reference,
                'providerEvidence' => $this->providerResult((string) $intent->parameters['public_id']),
                'title' => 'Browser portrait',
                'altText' => 'Model wearing a navy jacket',
                'overrideDuplicate' => false,
                'overrideReason' => null,
            ])
            ->assertReturned(fn (array $result): bool => $result['status'] === 'completed');

        $this->assertDatabaseHas('media_assets', ['internal_title' => 'Browser portrait', 'state' => 'ready']);
    }

    public function test_livewire_accepts_real_cloudinary_shape_without_a_mime_type_field(): void
    {
        $actor = $this->cms();
        $intent = app(CreateUploadIntent::class)->handle($actor, 'image', 'image/png', 1234);
        $evidence = $this->providerResult((string) $intent->parameters['public_id']);
        $evidence['format'] = 'png';
        unset($evidence['mime_type']);

        Livewire::actingAs($actor)->test(MediaLibrary::class)
            ->call('confirmUpload', [
                'intentReference' => $intent->reference,
                'providerEvidence' => $evidence,
                'title' => 'Logo PNG',
                'altText' => null,
                'overrideDuplicate' => false,
                'overrideReason' => null,
            ])
            ->assertReturned(fn (array $result): bool => $result['status'] === 'completed');

        $this->assertDatabaseHas('media_assets', ['internal_title' => 'Logo PNG', 'mime_type' => 'image/png', 'state' => 'ready']);
    }

    public function test_derived_provider_mime_must_still_match_the_bound_intent(): void
    {
        $actor = $this->cms();
        $intent = app(CreateUploadIntent::class)->handle($actor, 'image', 'image/jpeg', 1234);
        $evidence = $this->providerResult((string) $intent->parameters['public_id']);
        $evidence['format'] = 'png';
        unset($evidence['mime_type']);

        Livewire::actingAs($actor)->test(MediaLibrary::class)
            ->call('confirmUpload', [
                'intentReference' => $intent->reference,
                'providerEvidence' => $evidence,
                'title' => 'Wrong format',
            ])
            ->assertReturned(fn (array $result): bool => $result['status'] === 'failed');

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_deployed_array_first_payload_is_domain_handled_and_never_type_errors(): void
    {
        $actor = $this->cms();
        $intent = app(CreateUploadIntent::class)->handle($actor, 'image', 'image/jpeg', 1234);
        $evidence = $this->providerResult((string) $intent->parameters['public_id']);
        $evidence['context'] = ['custom' => ['intent_reference' => $intent->reference]];

        Livewire::actingAs($actor)->test(MediaLibrary::class)
            ->call('confirmUpload', $evidence, 'Legacy browser portrait', null, false, null)
            ->assertReturned(fn (array $result): bool => $result['status'] === 'completed');

        Livewire::actingAs($actor)->test(MediaLibrary::class)
            ->call('confirmUpload', $evidence, 'Replay', null, false, null)
            ->assertReturned(fn (array $result): bool => $result['status'] === 'failed' && str_starts_with($result['reference'], 'WT-'));
    }

    public function test_missing_or_malformed_livewire_intent_is_domain_handled_inline(): void
    {
        $actor = $this->cms();
        $result = $this->providerResult('testing/media/unbound');

        foreach ([$result, ['intentReference' => [], 'providerEvidence' => $result, 'title' => 'Malformed']] as $payload) {
            Livewire::actingAs($actor)->test(MediaLibrary::class)
                ->call('confirmUpload', $payload, 'Missing reference')
                ->assertReturned(fn (array $response): bool => $response['status'] === 'failed'
                    && str_contains($response['message'], 'support reference WT-'));
        }

        $this->assertDatabaseCount('media_assets', 0);
    }

    /** @return array<string, mixed> */
    private function providerResult(string $publicId): array
    {
        return ['asset_id' => 'asset-secure', 'public_id' => $publicId, 'version' => 1, 'resource_type' => 'image', 'format' => 'jpg', 'mime_type' => 'image/jpeg', 'original_filename' => 'portrait.jpg', 'width' => 1200, 'height' => 1500, 'bytes' => 1234, 'checksum' => 'secure'];
    }

    private function expectFailure(string $code, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Confirmation unexpectedly succeeded.');
        } catch (MediaUploadFailure $failure) {
            $this->assertSame($code, $failure->failureCode);
        }
    }

    private function cms(): User
    {
        $user = User::factory()->create();
        app(ControlledRoleMutation::class)->run(fn () => $user->assignRole(RoleRegistry::CMS_MANAGER));

        return $user;
    }
}
