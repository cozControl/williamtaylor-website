<?php

namespace Tests\Fakes;

use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Data\UploadIntent;
use App\Domain\Media\Data\VerifiedProviderAsset;

final class FakeMediaProvider implements MediaProvider
{
    public bool $exists = true;

    public int $intentCount = 0;

    public int $verificationCount = 0;

    public function createUploadIntent(array $request): UploadIntent
    {
        $this->intentCount++;

        return new UploadIntent('https://fake.invalid/upload', ['public_id' => $request['public_id'], 'signature' => 'redacted-test-signature'], now()->addMinutes(5)->timestamp);
    }

    public function verifyUploadResult(array $result): VerifiedProviderAsset
    {
        $this->verificationCount++;
        if (($result['valid'] ?? true) !== true) {
            throw new \RuntimeException('Invalid fake result.');
        }

        return new VerifiedProviderAsset($result['asset_id'], $result['public_id'], (string) ($result['version'] ?? 1), $result['resource_type'], 'upload', $result['format'], $result['mime_type'], $result['original_filename'], $result['width'] ?? null, $result['height'] ?? null, $result['duration_ms'] ?? null, $result['bytes'], $result['checksum'] ?? null);
    }

    public function deliveryUrl(string $publicId, string $resourceType, string $profile, ?float $focalX = null, ?float $focalY = null): string
    {
        return "https://fake.invalid/{$resourceType}/{$profile}/{$publicId}";
    }

    public function privateDeliveryUrl(string $publicId, string $resourceType, string $profile, int $expiresAt): string
    {
        return "https://fake.invalid/private/{$publicId}?expires={$expiresAt}&signature=test";
    }

    public function assetExists(string $assetId): bool
    {
        return $this->exists;
    }

    public function synchronizeFactorySource(array $entry, string $publicId): VerifiedProviderAsset
    {
        return new VerifiedProviderAsset(
            'factory-test-asset', $publicId, '1', (string) $entry['resource_type'], 'upload',
            pathinfo((string) $entry['source'], PATHINFO_EXTENSION) ?: 'bin', (string) $entry['mime_type'],
            basename((string) $entry['source']), null, null, null, 1, $entry['sha256'] ?? null,
        );
    }
}
