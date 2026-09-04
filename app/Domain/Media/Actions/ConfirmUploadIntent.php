<?php

namespace App\Domain\Media\Actions;

use App\Domain\Media\Exceptions\MediaUploadFailure;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUploadIntent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ConfirmUploadIntent
{
    public function __construct(private ConfirmUploadedAsset $confirm) {}

    /** @param array<string, mixed> $result */
    public function handle(User $actor, string $reference, array $result, string $title, ?string $altText, bool $overrideDuplicate = false, ?string $overrideReason = null): MediaAsset
    {
        return DB::transaction(function () use ($actor, $reference, $result, $title, $altText, $overrideDuplicate, $overrideReason): MediaAsset {
            if ($reference === '') {
                throw new MediaUploadFailure('media.intent_missing', 'Upload intent reference is missing.');
            }

            $intent = MediaUploadIntent::query()->whereKey($reference)->lockForUpdate()->first();
            if (! $intent || $intent->actor_id !== $actor->getKey() || $intent->provider !== config('media.provider')) {
                throw new MediaUploadFailure('media.intent_invalid', 'Upload intent is unavailable for this actor and provider.');
            }
            if ($intent->consumed_at !== null) {
                throw new MediaUploadFailure('media.intent_consumed', 'Upload intent was already consumed.');
            }
            if ($intent->expires_at->isPast()) {
                throw new MediaUploadFailure('media.intent_expired', 'Upload intent has expired.');
            }
            if (($result['public_id'] ?? null) !== $intent->public_id) {
                throw new MediaUploadFailure('media.provider_public_id_invalid', 'Provider public ID does not match the upload intent.');
            }
            if (($result['resource_type'] ?? null) !== $intent->resource_type) {
                throw new MediaUploadFailure('media.provider_resource_type_invalid', 'Provider resource type does not match the upload intent.');
            }
            $providerMimeType = $this->providerMimeType($result);
            if ($providerMimeType !== $intent->mime_type) {
                throw new MediaUploadFailure('media.provider_mime_type_invalid', 'Provider MIME type does not match the upload intent.');
            }
            $result['mime_type'] = $providerMimeType;
            if ((int) ($result['bytes'] ?? -1) !== $intent->expected_bytes) {
                throw new MediaUploadFailure('media.provider_bytes_invalid', 'Provider byte count does not match the upload intent.');
            }

            $asset = $this->confirm->handle($actor, $result, $title, $altText, $overrideDuplicate, $overrideReason);
            $intent->forceFill(['consumed_at' => now('UTC')])->save();

            return $asset;
        }, 3);
    }

    /** @param array<string, mixed> $result */
    private function providerMimeType(array $result): ?string
    {
        if (isset($result['mime_type']) && is_string($result['mime_type']) && $result['mime_type'] !== '') {
            return strtolower($result['mime_type']);
        }

        $resourceType = $result['resource_type'] ?? null;
        $format = strtolower((string) ($result['format'] ?? ''));
        $format = $format === 'jpg' ? 'jpeg' : $format;

        return match (true) {
            $resourceType === 'image' && in_array($format, ['jpeg', 'png', 'webp', 'avif'], true) => 'image/'.$format,
            $resourceType === 'video' && in_array($format, ['mp4', 'webm'], true) => 'video/'.$format,
            default => null,
        };
    }
}
