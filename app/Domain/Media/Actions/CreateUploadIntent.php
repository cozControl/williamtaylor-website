<?php

namespace App\Domain\Media\Actions;

use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Data\UploadIntent;
use App\Domain\Media\Support\MediaFilePolicy;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateUploadIntent
{
    public function __construct(private MediaProvider $provider) {}

    public function handle(User $actor, string $resourceType, string $mimeType, int $expectedBytes): UploadIntent
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::MEDIA_UPLOAD);
        $allowedMime = $resourceType === 'image' ? ['image/jpeg', 'image/png', 'image/webp', 'image/avif'] : ['video/mp4', 'video/webm'];
        $max = $resourceType === 'image' ? MediaFilePolicy::IMAGE_MAX_BYTES : MediaFilePolicy::VIDEO_MAX_BYTES;
        if (! in_array($resourceType, ['image', 'video'], true) || ! in_array($mimeType, $allowedMime, true) || $expectedBytes < 1 || $expectedBytes > $max) {
            throw new InvalidArgumentException('Upload intent violates the approved media policy.');
        }
        $publicId = rtrim((string) config('media.cloudinary.folder'), '/').'/'.now()->format('Y/m').'/'.Str::ulid();

        return $this->provider->createUploadIntent(['resource_type' => $resourceType, 'mime_type' => $mimeType, 'expected_bytes' => $expectedBytes, 'public_id' => $publicId]);
    }
}
