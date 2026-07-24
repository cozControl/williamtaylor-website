<?php

namespace App\Infrastructure\Media\Cloudinary;

use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Data\UploadIntent;
use App\Domain\Media\Data\VerifiedProviderAsset;
use App\Domain\Media\Support\TransformationProfiles;
use Cloudinary\Api\ApiUtils;
use Cloudinary\Cloudinary;
use RuntimeException;

final class CloudinaryMediaProvider implements MediaProvider
{
    private function sdk(): Cloudinary
    {
        $config = config('media.cloudinary');
        if (! is_array($config) || ! $config['cloud_name'] || ! $config['api_key'] || ! $config['api_secret']) {
            throw new RuntimeException('Cloudinary media provider is not configured.');
        }

        return new Cloudinary(['cloud' => ['cloud_name' => $config['cloud_name'], 'api_key' => $config['api_key'], 'api_secret' => $config['api_secret']], 'url' => ['secure' => true]]);
    }

    public function createUploadIntent(array $request): UploadIntent
    {
        $this->sdk();
        $timestamp = (int) now()->timestamp;
        $folder = (string) config('media.cloudinary.folder');
        $parameters = ['timestamp' => $timestamp, 'folder' => $folder, 'public_id' => $request['public_id'], 'type' => 'upload'];
        $parameters['signature'] = ApiUtils::signParameters($parameters, (string) config('media.cloudinary.api_secret'));
        $parameters['api_key'] = (string) config('media.cloudinary.api_key');

        return new UploadIntent('https://api.cloudinary.com/v1_1/'.rawurlencode((string) config('media.cloudinary.cloud_name')).'/'.$request['resource_type'].'/upload', $parameters, $timestamp + 300);
    }

    public function verifyUploadResult(array $result): VerifiedProviderAsset
    {
        $this->sdk();
        $signature = (string) ($result['signature'] ?? '');
        $timestamp = (int) ($result['created_at_timestamp'] ?? 0);
        if ($timestamp < now()->subMinutes(5)->timestamp || ! hash_equals(ApiUtils::signParameters(['public_id' => $result['public_id'] ?? '', 'version' => $result['version'] ?? ''], (string) config('media.cloudinary.api_secret')), $signature)) {
            throw new RuntimeException('Provider upload evidence is invalid or stale.');
        }
        $folder = rtrim((string) config('media.cloudinary.folder'), '/').'/';
        if (! str_starts_with((string) $result['public_id'], $folder)) {
            throw new RuntimeException('Provider upload folder is invalid.');
        }

        return new VerifiedProviderAsset((string) $result['asset_id'], (string) $result['public_id'], (string) $result['version'], (string) $result['resource_type'], (string) ($result['type'] ?? 'upload'), strtolower((string) $result['format']), (string) $result['mime_type'], (string) $result['original_filename'], isset($result['width']) ? (int) $result['width'] : null, isset($result['height']) ? (int) $result['height'] : null, isset($result['duration']) ? (int) round((float) $result['duration'] * 1000) : null, (int) $result['bytes'], $result['etag'] ?? null);
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
}
