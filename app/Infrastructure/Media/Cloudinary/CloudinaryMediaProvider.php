<?php

namespace App\Infrastructure\Media\Cloudinary;

use App\Domain\Factory\Services\FactoryManifest;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Data\UploadIntent;
use App\Domain\Media\Data\VerifiedProviderAsset;
use App\Domain\Media\Exceptions\MediaUploadFailure;
use App\Domain\Media\Support\TransformationProfiles;
use Carbon\CarbonImmutable;
use Cloudinary\Api\ApiUtils;
use Cloudinary\Cloudinary;

final class CloudinaryMediaProvider implements MediaProvider
{
    private function sdk(): Cloudinary
    {
        $config = config('media.cloudinary');
        if (! is_array($config) || ! $config['cloud_name'] || ! $config['api_key'] || ! $config['api_secret']) {
            throw new MediaUploadFailure('media.provider_configuration_invalid', 'Cloudinary media provider is not configured.');
        }

        return new Cloudinary(['cloud' => ['cloud_name' => $config['cloud_name'], 'api_key' => $config['api_key'], 'api_secret' => $config['api_secret']], 'url' => ['secure' => true]]);
    }

    public function createUploadIntent(array $request): UploadIntent
    {
        $this->sdk();
        $timestamp = (int) now()->timestamp;
        $parameters = [
            'timestamp' => $timestamp,
            'public_id' => $request['public_id'],
            'type' => 'upload',
            'context' => 'intent_reference='.(string) $request['intent_reference'],
        ];
        $parameters['signature'] = ApiUtils::signParameters($parameters, (string) config('media.cloudinary.api_secret'));
        $parameters['api_key'] = (string) config('media.cloudinary.api_key');

        return new UploadIntent('https://api.cloudinary.com/v1_1/'.rawurlencode((string) config('media.cloudinary.cloud_name')).'/'.$request['resource_type'].'/upload', $parameters, $timestamp + 300);
    }

    public function verifyUploadResult(array $result): VerifiedProviderAsset
    {
        $this->sdk();
        foreach (['asset_id', 'public_id', 'version', 'resource_type', 'format', 'bytes', 'signature', 'created_at'] as $field) {
            if (! array_key_exists($field, $result) || $result[$field] === '') {
                throw new MediaUploadFailure('media.provider_response_incomplete', 'Provider upload evidence is incomplete.');
            }
        }
        $signature = (string) ($result['signature'] ?? '');
        try {
            $createdAt = CarbonImmutable::parse((string) $result['created_at']);
        } catch (\Throwable $exception) {
            throw new MediaUploadFailure('media.provider_timestamp_invalid', 'Provider timestamp is invalid.', $exception);
        }
        if ($createdAt->isBefore(now()->subMinutes(5)) || $createdAt->isAfter(now()->addMinute())) {
            throw new MediaUploadFailure('media.provider_timestamp_invalid', 'Provider timestamp is outside the confirmation window.');
        }
        $expectedSignature = ApiUtils::signParameters([
            'public_id' => (string) $result['public_id'],
            'version' => (string) $result['version'],
        ], (string) config('media.cloudinary.api_secret'));
        if (! hash_equals($expectedSignature, $signature)) {
            throw new MediaUploadFailure('media.provider_signature_invalid', 'Provider upload signature is invalid.');
        }
        $folder = rtrim((string) config('media.cloudinary.folder'), '/').'/';
        if (! str_starts_with((string) $result['public_id'], $folder)) {
            throw new MediaUploadFailure('media.provider_folder_mismatch', 'Provider upload folder is invalid.');
        }

        if (($result['type'] ?? 'upload') !== 'upload') {
            throw new MediaUploadFailure('media.provider_delivery_type_invalid', 'Provider delivery type is invalid.');
        }

        $resourceType = (string) $result['resource_type'];
        $format = strtolower((string) $result['format']);
        $mimeFormat = $format === 'jpg' ? 'jpeg' : $format;
        $mimeType = (string) ($result['mime_type'] ?? ($resourceType === 'video' ? 'video/'.$mimeFormat : 'image/'.$mimeFormat));

        return new VerifiedProviderAsset((string) $result['asset_id'], (string) $result['public_id'], (string) $result['version'], $resourceType, (string) ($result['type'] ?? 'upload'), $format, $mimeType, (string) ($result['original_filename'] ?? $result['public_id']), isset($result['width']) ? (int) $result['width'] : null, isset($result['height']) ? (int) $result['height'] : null, isset($result['duration']) ? (int) round((float) $result['duration'] * 1000) : null, (int) $result['bytes'], $result['etag'] ?? null);
    }

    public function deliveryUrl(string $publicId, string $resourceType, string $profile, ?float $focalX = null, ?float $focalY = null): string
    {
        $settings = app(TransformationProfiles::class)->get($profile);
        $transformation = 'c_'.$settings['crop'].',w_'.$settings['width'].',h_'.$settings['height'].',q_'.$settings['quality'].',f_'.$settings['format'];
        if ($focalX !== null && $focalY !== null && $settings['crop'] === 'fill') {
            $transformation .= ',g_xy_center,x_'.$focalX.',y_'.$focalY;
        }

        return 'https://res.cloudinary.com/'.rawurlencode((string) config('media.cloudinary.cloud_name')).'/'.$resourceType.'/upload/'.$transformation.'/'.ltrim($publicId, '/');
    }

    public function privateDeliveryUrl(string $publicId, string $resourceType, string $profile, int $expiresAt): string
    {
        $this->sdk();
        $signature = ApiUtils::signParameters(['public_id' => $publicId, 'expires_at' => $expiresAt], (string) config('media.cloudinary.api_secret'));

        return 'https://res.cloudinary.com/'.rawurlencode((string) config('media.cloudinary.cloud_name')).'/'.$resourceType.'/authenticated/'.rawurlencode($publicId).'?expires_at='.$expiresAt.'&signature='.$signature;
    }

    public function assetExists(string $assetId): bool
    {
        try {
            $this->sdk()->adminApi()->assetByAssetId($assetId);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function synchronizeFactorySource(array $entry, string $publicId): VerifiedProviderAsset
    {
        $source = (string) $entry['source'];
        if (($entry['source_kind'] ?? null) === 'local') {
            $source = base_path($source);
        }
        $response = $this->sdk()->uploadApi()->upload($source, [
            'public_id' => $publicId,
            'resource_type' => (string) $entry['resource_type'],
            'overwrite' => false,
            'unique_filename' => false,
            'use_filename' => false,
            'tags' => [FactoryManifest::VERSION, (string) $entry['logical_key']],
        ]);
        $result = $response->getArrayCopy();

        return new VerifiedProviderAsset(
            (string) $result['asset_id'], (string) $result['public_id'], (string) $result['version'],
            (string) $result['resource_type'], (string) ($result['type'] ?? 'upload'),
            strtolower((string) $result['format']), (string) ($result['resource_type'] === 'video' ? 'video/'.$result['format'] : 'image/'.$result['format']),
            (string) ($result['original_filename'] ?? basename($source)),
            isset($result['width']) ? (int) $result['width'] : null,
            isset($result['height']) ? (int) $result['height'] : null,
            isset($result['duration']) ? (int) round((float) $result['duration'] * 1000) : null,
            (int) $result['bytes'], isset($result['etag']) ? (string) $result['etag'] : null,
            ['factory_version' => FactoryManifest::VERSION]
        );
    }
}
