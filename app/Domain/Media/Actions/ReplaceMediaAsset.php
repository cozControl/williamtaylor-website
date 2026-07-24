<?php

namespace App\Domain\Media\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Data\VerifiedProviderAsset;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaAssetVersion;
use App\Domain\Media\Support\ReplacementFingerprint;
use App\Domain\Media\Support\ReplacementProposalStore;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ReplaceMediaAsset
{
    public function __construct(private MediaProvider $provider, private RecordAuditEvent $audit) {}

    /** @param array<string, mixed> $result */
    public function handle(User $actor, MediaAsset $asset, array $result, string $reason): void
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::MEDIA_REPLACE);
        $this->handleVerified($actor, $asset, $this->provider->verifyUploadResult($result), $reason);
    }

    public function handleProposal(User $actor, MediaAsset $asset, string $token, string $reason): void
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::MEDIA_REPLACE);
        $proposal = app(ReplacementProposalStore::class)->pull($actor, $asset, $token);
        $currentFingerprint = app(ReplacementFingerprint::class)->for($asset->fresh(), $proposal['verified']);
        if (! hash_equals($proposal['fingerprint'], $currentFingerprint)) {
            throw new DomainException('Replacement state changed. Review a new comparison and confirm again.');
        }
        $this->handleVerified($actor, $asset, $proposal['verified'], $reason);
        app(ReplacementProposalStore::class)->forget($token);
    }

    private function handleVerified(User $actor, MediaAsset $asset, VerifiedProviderAsset $verified, string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Replacement reason is required.');
        }
        if ($verified->resourceType !== $asset->resource_type->value) {
            throw new InvalidArgumentException('Replacement resource type must match the logical asset.');
        }
        DB::transaction(function () use ($actor, $asset, $verified, $reason): void {
            $current = $asset->versions()->where('is_current', true)->lockForUpdate()->firstOrFail();
            $next = $asset->versions()->max('version_number') + 1;
            $current->newQuery()->whereKey($current->getKey())->update(['is_current' => false]);
            MediaAssetVersion::query()->create(['id' => (string) Str::ulid(), 'media_asset_id' => $asset->id, 'version_number' => $next, 'provider_asset_id' => $verified->assetId, 'provider_public_id' => $verified->publicId, 'provider_version' => $verified->version, 'resource_type' => $verified->resourceType, 'format' => $verified->format, 'mime_type' => $verified->mimeType, 'width' => $verified->width, 'height' => $verified->height, 'duration_ms' => $verified->durationMs, 'bytes' => $verified->bytes, 'checksum' => $verified->checksum, 'provider_metadata' => $verified->metadata, 'uploaded_by' => $actor->getKey(), 'replacement_reason' => $reason, 'is_current' => true, 'created_at' => now()]);
            $asset->update(['provider_asset_id' => $verified->assetId, 'provider_public_id' => $verified->publicId, 'provider_version' => $verified->version, 'format' => $verified->format, 'mime_type' => $verified->mimeType, 'width' => $verified->width, 'height' => $verified->height, 'duration_ms' => $verified->durationMs, 'bytes' => $verified->bytes, 'checksum' => $verified->checksum]);
            $this->audit->handle('media.asset.replaced', $asset, $actor, ['version' => $current->version_number, 'provider_asset_id' => $current->provider_asset_id], ['version' => $next, 'provider_asset_id' => $verified->assetId], PermissionRegistry::MEDIA_REPLACE, $reason);
        }, 3);
    }
}
