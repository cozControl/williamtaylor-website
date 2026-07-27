<?php

namespace App\Domain\Factory\Services;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaAssetVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class FactoryMediaSynchronizer
{
    public function __construct(
        private readonly FactoryManifest $manifest,
        private readonly MediaProvider $provider,
        private readonly RecordAuditEvent $audit,
    ) {}

    /** @return array{upload:int,reuse:int,deferred:int,drift:int} */
    public function plan(?string $only = null, bool $includeRemote = false): array
    {
        $result = ['upload' => 0, 'reuse' => 0, 'deferred' => 0, 'drift' => 0];
        foreach ($this->selected($only) as $entry) {
            $this->validate($entry);
            if ($entry['source_kind'] === 'remote' && ! $includeRemote) {
                $result['deferred']++;

                continue;
            }
            $existing = MediaAsset::query()->where('provider_public_id', $this->publicId($entry))->first();
            if ($existing === null) {
                $result['upload']++;
            } elseif ($entry['sha256'] !== null && $existing->checksum !== $entry['sha256']) {
                $result['drift']++;
            } else {
                $result['reuse']++;
            }
        }

        return $result;
    }

    /** @return array{upload:int,reuse:int,deferred:int,drift:int} */
    public function apply(User $actor, ?string $only = null, bool $includeRemote = false): array
    {
        if (config('media.provider') !== 'cloudinary' && config('media.provider') !== 'deterministic') {
            throw new RuntimeException('Factory Media apply requires the approved cloudinary or deterministic provider.');
        }
        if (config('media.provider') === 'cloudinary') {
            foreach (['cloud_name', 'api_key', 'api_secret'] as $key) {
                if (! is_string(config("media.cloudinary.{$key}")) || trim((string) config("media.cloudinary.{$key}")) === '') {
                    throw new RuntimeException('Cloudinary provider credentials are incomplete.');
                }
            }
        }
        $plan = $this->plan($only, $includeRemote);
        if ($plan['drift'] > 0) {
            throw new RuntimeException('Factory Media drift was detected. Existing provider assets will not be overwritten.');
        }
        foreach ($this->selected($only) as $entry) {
            if ($entry['source_kind'] === 'remote' && ! $includeRemote) {
                continue;
            }
            $publicId = $this->publicId($entry);
            if (MediaAsset::query()->where('provider_public_id', $publicId)->exists()) {
                continue;
            }
            $verified = $this->provider->synchronizeFactorySource($entry, $publicId);
            if ($verified->publicId !== $publicId || $verified->resourceType !== $entry['resource_type']) {
                throw new RuntimeException('Provider facts did not match the factory manifest.');
            }
            DB::transaction(function () use ($actor, $entry, $verified): void {
                $asset = MediaAsset::query()->create([
                    'provider' => (string) config('media.provider'), 'provider_asset_id' => $verified->assetId,
                    'provider_public_id' => $verified->publicId, 'provider_version' => $verified->version,
                    'resource_type' => $verified->resourceType, 'delivery_type' => $verified->deliveryType,
                    'format' => $verified->format, 'mime_type' => $verified->mimeType,
                    'original_filename' => $verified->filename, 'internal_title' => $entry['title'],
                    'default_alt_text' => $entry['alt_text'], 'credit' => $entry['credit'] ?? null,
                    'rights_source' => 'client-supplied', 'rights_notes' => $entry['rights'],
                    'tags' => [FactoryManifest::VERSION, $entry['logical_key']], 'collection_key' => 'factory',
                    'width' => $verified->width, 'height' => $verified->height, 'duration_ms' => $verified->durationMs,
                    'bytes' => $verified->bytes, 'checksum' => $entry['sha256'] ?? $verified->checksum,
                    'accessibility_classification' => $entry['decorative'] ? 'decorative' : 'informative',
                    'is_decorative' => $entry['decorative'], 'state' => 'ready',
                    'provider_metadata' => ['factory_version' => FactoryManifest::VERSION, 'logical_key' => $entry['logical_key']],
                    'uploaded_by' => $actor->getKey(), 'confirmed_at' => now('UTC'),
                ]);
                MediaAssetVersion::query()->create([
                    'media_asset_id' => $asset->getKey(), 'version_number' => 1,
                    'provider_asset_id' => $verified->assetId, 'provider_public_id' => $verified->publicId,
                    'provider_version' => $verified->version, 'resource_type' => $verified->resourceType,
                    'format' => $verified->format, 'mime_type' => $verified->mimeType,
                    'width' => $verified->width, 'height' => $verified->height, 'duration_ms' => $verified->durationMs,
                    'bytes' => $verified->bytes, 'checksum' => $entry['sha256'] ?? $verified->checksum,
                    'provider_metadata' => ['factory_version' => FactoryManifest::VERSION],
                    'uploaded_by' => $actor->getKey(), 'is_current' => true, 'created_at' => now('UTC'),
                ]);
                $this->audit->handle('factory.media.synchronized', $asset, $actor, null, [
                    'factory_version' => FactoryManifest::VERSION, 'logical_key' => $entry['logical_key'],
                    'resource_type' => $entry['resource_type'], 'bytes' => $verified->bytes,
                ], null, 'Explicit factory Media synchronization');
            }, 3);
        }

        return $this->plan($only, $includeRemote);
    }

    /** @return list<array<string, mixed>> */
    private function selected(?string $only): array
    {
        $entries = $this->manifest->media();
        if ($only !== null && $only !== '') {
            $entries = array_values(array_filter($entries, fn (array $entry): bool => $entry['logical_key'] === $only));
            if ($entries === []) {
                throw new InvalidArgumentException("Unknown factory Media key [{$only}].");
            }
        }

        return $entries;
    }

    /** @param array<string, mixed> $entry */
    private function validate(array $entry): void
    {
        if ($entry['factory_version'] !== FactoryManifest::VERSION || ! in_array($entry['resource_type'], ['image', 'video'], true)) {
            throw new InvalidArgumentException('Factory Media entry is invalid.');
        }
        if ($entry['source_kind'] === 'local') {
            $path = base_path($entry['source']);
            if (! is_file($path)) {
                throw new RuntimeException("Required factory Media source [{$entry['logical_key']}] is missing.");
            }
            $actualChecksum = hash_file('sha256', $path);
            if ($actualChecksum === false) {
                throw new RuntimeException("Factory Media source could not be hashed [{$entry['logical_key']}].");
            }
            if (! hash_equals($entry['sha256'], $actualChecksum)) {
                throw new RuntimeException("Factory Media checksum drift [{$entry['logical_key']}].");
            }

            return;
        }
        $url = parse_url($entry['source']);
        if (($url['scheme'] ?? null) !== 'https' || ! in_array(strtolower((string) ($url['host'] ?? '')), config('factory.remote_video_hosts'), true)) {
            throw new RuntimeException("Factory remote Media host is not allowlisted [{$entry['logical_key']}].");
        }
    }

    /** @param array<string, mixed> $entry */
    private function publicId(array $entry): string
    {
        $folder = trim((string) config('media.cloudinary.folder'), '/');
        if ($folder === '' || ! str_contains($folder, 'william-taylor/media')) {
            throw new RuntimeException('Cloudinary environment folder must be a scoped william-taylor/media path.');
        }

        return $folder.'/factory/william-taylor-v1/'.$entry['provider_suffix'];
    }
}
