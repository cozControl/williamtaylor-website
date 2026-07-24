<?php

namespace App\Domain\Media\Support;

use App\Domain\Media\Data\VerifiedProviderAsset;
use App\Domain\Media\Models\MediaAsset;

final class ReplacementFingerprint
{
    public function for(MediaAsset $asset, VerifiedProviderAsset $proposed): string
    {
        $current = $asset->versions()->where('is_current', true)->firstOrFail();
        $usageKeys = $asset->usages()->orderBy('id')->get(['id', 'owner_type', 'owner_identifier', 'field_role', 'sort_order'])
            ->map(fn ($usage): array => $usage->only(['id', 'owner_type', 'owner_identifier', 'field_role', 'sort_order']))
            ->all();

        return hash('sha256', json_encode([
            'asset_id' => $asset->getKey(),
            'current_version_id' => $current->getKey(),
            'current_provider_asset_id' => $current->provider_asset_id,
            'proposed' => [
                'asset_id' => $proposed->assetId,
                'public_id' => $proposed->publicId,
                'version' => $proposed->version,
                'resource_type' => $proposed->resourceType,
                'format' => $proposed->format,
                'width' => $proposed->width,
                'height' => $proposed->height,
                'duration_ms' => $proposed->durationMs,
                'bytes' => $proposed->bytes,
                'checksum' => $proposed->checksum,
            ],
            'usages' => $usageKeys,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
