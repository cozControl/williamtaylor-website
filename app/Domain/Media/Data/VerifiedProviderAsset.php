<?php

namespace App\Domain\Media\Data;

final readonly class VerifiedProviderAsset
{
    /** @param array<string, mixed> $metadata */
    public function __construct(public string $assetId, public string $publicId, public string $version, public string $resourceType, public string $deliveryType, public string $format, public string $mimeType, public string $filename, public ?int $width, public ?int $height, public ?int $durationMs, public int $bytes, public ?string $checksum, public array $metadata = []) {}
}
