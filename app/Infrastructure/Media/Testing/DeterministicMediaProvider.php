<?php

namespace App\Infrastructure\Media\Testing;

use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Data\UploadIntent;
use App\Domain\Media\Data\VerifiedProviderAsset;
use RuntimeException;

final class DeterministicMediaProvider implements MediaProvider
{
    public function createUploadIntent(array $request): UploadIntent
    {
        if (app()->environment('production')) {
            throw new RuntimeException('The deterministic media provider is prohibited in production.');
        }
        $expires = (int) now()->addMinutes(5)->timestamp;
        $token = hash_hmac('sha256', $request['public_id'].'|'.$request['resource_type'].'|'.$expires, (string) config('app.key'));

        return new UploadIntent('evidence://direct-upload', [
            'public_id' => $request['public_id'],
            'resource_type' => $request['resource_type'],
            'intent_reference' => $request['intent_reference'],
            'expires_at' => $expires,
            'evidence_token' => $token,
        ], $expires);
    }

    public function verifyUploadResult(array $result): VerifiedProviderAsset
    {
        $expires = (int) ($result['expires_at'] ?? 0);
        $expected = hash_hmac('sha256', ($result['public_id'] ?? '').'|'.($result['resource_type'] ?? '').'|'.$expires, (string) config('app.key'));
        if ($expires < now()->timestamp || ! hash_equals($expected, (string) ($result['evidence_token'] ?? ''))) {
            throw new RuntimeException('Provider upload evidence is invalid or stale.');
        }
        if (($result['simulate_failure'] ?? false) === true) {
            throw new RuntimeException('The controlled provider rejected this upload.');
        }

        return new VerifiedProviderAsset(
            (string) $result['asset_id'],
            (string) $result['public_id'],
            (string) ($result['version'] ?? 1),
            (string) $result['resource_type'],
            'upload',
            (string) $result['format'],
            (string) $result['mime_type'],
            (string) $result['original_filename'],
            isset($result['width']) ? (int) $result['width'] : null,
            isset($result['height']) ? (int) $result['height'] : null,
            isset($result['duration_ms']) ? (int) $result['duration_ms'] : null,
            (int) $result['bytes'],
            $result['checksum'] ?? null,
        );
    }

    public function deliveryUrl(string $publicId, string $resourceType, string $profile, ?float $focalX = null, ?float $focalY = null): string
    {
        return 'data:image/svg+xml,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600"><rect width="100%" height="100%" fill="#ded9cd"/><text x="50%" y="50%" text-anchor="middle">Media preview</text></svg>');
    }

    public function privateDeliveryUrl(string $publicId, string $resourceType, string $profile, int $expiresAt): string
    {
        return 'https://example.invalid/private?expires='.$expiresAt.'&signature=controlled';
    }

    public function assetExists(string $assetId): bool
    {
        return ! str_contains($assetId, 'missing');
    }

    public function synchronizeFactorySource(array $entry, string $publicId): VerifiedProviderAsset
    {
        $source = (string) $entry['source'];
        $bytes = $entry['source_kind'] === 'local' ? filesize(base_path($source)) : 1024;
        $checksum = $entry['sha256'] ?? hash('sha256', $source);

        return new VerifiedProviderAsset(
            'factory-'.substr(hash('sha256', $publicId), 0, 32), $publicId, '1',
            (string) $entry['resource_type'], 'upload', pathinfo($source, PATHINFO_EXTENSION) ?: 'mp4',
            (string) $entry['mime_type'], basename($source), null, null,
            $entry['resource_type'] === 'video' ? 1000 : null, (int) $bytes, (string) $checksum,
            ['factory' => true]
        );
    }
}
