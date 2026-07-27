<?php

namespace App\Domain\Media\Contracts;

use App\Domain\Media\Data\UploadIntent;
use App\Domain\Media\Data\VerifiedProviderAsset;

interface MediaProvider
{
    /** @param array<string, mixed> $request */
    public function createUploadIntent(array $request): UploadIntent;

    /** @param array<string, mixed> $result */
    public function verifyUploadResult(array $result): VerifiedProviderAsset;

    public function deliveryUrl(string $publicId, string $resourceType, string $profile, ?float $focalX = null, ?float $focalY = null): string;

    public function privateDeliveryUrl(string $publicId, string $resourceType, string $profile, int $expiresAt): string;

    public function assetExists(string $assetId): bool;

    /** @param array<string, mixed> $entry */
    public function synchronizeFactorySource(array $entry, string $publicId): VerifiedProviderAsset;
}
