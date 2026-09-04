<?php

namespace App\Domain\Media\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Exceptions\ExactDuplicateMediaException;
use App\Domain\Media\Exceptions\MediaUploadFailure;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaAssetVersion;
use App\Domain\Media\Support\MediaFilePolicy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;

final class ConfirmUploadedAsset
{
    public function __construct(private MediaProvider $provider, private RecordAuditEvent $audit) {}

    /** @param array<string, mixed> $result */
    public function handle(User $actor, array $result, string $title, ?string $altText, bool $overrideDuplicate = false, ?string $overrideReason = null): MediaAsset
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::MEDIA_UPLOAD);
        $verified = $this->provider->verifyUploadResult($result);
        $formats = $verified->resourceType === 'image' ? MediaFilePolicy::IMAGE_FORMATS : MediaFilePolicy::VIDEO_FORMATS;
        $max = $verified->resourceType === 'image' ? MediaFilePolicy::IMAGE_MAX_BYTES : MediaFilePolicy::VIDEO_MAX_BYTES;
        if (! in_array($verified->format, $formats, true) || $verified->bytes > $max || ! in_array($verified->resourceType, ['image', 'video'], true) || $verified->deliveryType !== 'upload') {
            throw new MediaUploadFailure(
                ! in_array($verified->deliveryType, ['upload'], true) ? 'media.provider_delivery_type_invalid' : (! in_array($verified->format, $formats, true) ? 'media.provider_format_invalid' : 'media.provider_bytes_invalid'),
                'Uploaded media violates the approved file policy.',
            );
        }

        $duplicate = $verified->checksum === null ? null : MediaAsset::query()
            ->where('checksum', $verified->checksum)
            ->where('provider_asset_id', '!=', $verified->assetId)
            ->first();
        if ($duplicate && ! $overrideDuplicate) {
            throw new ExactDuplicateMediaException($duplicate);
        }
        if ($duplicate && trim((string) $overrideReason) === '') {
            throw new RuntimeException('A reason is required to create a separate logical asset from an exact duplicate.');
        }

        return DB::transaction(function () use ($actor, $verified, $title, $altText, $duplicate, $overrideReason): MediaAsset {
            $existing = MediaAsset::query()->where('provider_asset_id', $verified->assetId)->first();
            if ($existing) {
                return $existing;
            }
            $asset = MediaAsset::query()->create(['provider_asset_id' => $verified->assetId, 'provider_public_id' => $verified->publicId, 'provider_version' => $verified->version, 'resource_type' => MediaResourceType::from($verified->resourceType), 'format' => $verified->format, 'mime_type' => $verified->mimeType, 'original_filename' => $verified->filename, 'internal_title' => $title, 'default_alt_text' => $altText, 'width' => $verified->width, 'height' => $verified->height, 'duration_ms' => $verified->durationMs, 'bytes' => $verified->bytes, 'checksum' => $verified->checksum, 'accessibility_classification' => AccessibilityClassification::Informative, 'state' => MediaAssetState::Ready, 'provider_metadata' => $verified->metadata, 'uploaded_by' => $actor->getKey(), 'confirmed_at' => now()]);
            MediaAssetVersion::query()->create(['id' => (string) Str::ulid(), 'media_asset_id' => $asset->id, 'version_number' => 1, 'provider_asset_id' => $verified->assetId, 'provider_public_id' => $verified->publicId, 'provider_version' => $verified->version, 'resource_type' => $verified->resourceType, 'format' => $verified->format, 'mime_type' => $verified->mimeType, 'width' => $verified->width, 'height' => $verified->height, 'duration_ms' => $verified->durationMs, 'bytes' => $verified->bytes, 'checksum' => $verified->checksum, 'provider_metadata' => $verified->metadata, 'uploaded_by' => $actor->getKey(), 'is_current' => true, 'created_at' => now()]);
            $this->audit->handle('media.asset.confirmed', $asset, $actor, null, ['version' => 1, 'resource_type' => $verified->resourceType], PermissionRegistry::MEDIA_UPLOAD);
            if ($duplicate) {
                $this->audit->handle('media.asset.duplicate-override', $asset, $actor, ['duplicate_asset_id' => $duplicate->getKey()], ['separate_asset_id' => $asset->getKey()], PermissionRegistry::MEDIA_UPLOAD, $overrideReason);
            }

            return $asset;
        }, 3);
    }
}
