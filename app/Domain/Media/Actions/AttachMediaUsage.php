<?php

namespace App\Domain\Media\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;

final class AttachMediaUsage
{
    public function __construct(private RecordAuditEvent $audit) {}

    public function handle(User $actor, MediaAsset $asset, string $ownerType, string $ownerId, string $fieldRole, ?string $alt = null, ?bool $decorative = null, int $order = 0): MediaUsage
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::MEDIA_EDIT);
        if ($asset->state !== MediaAssetState::Ready) {
            throw new RuntimeException('Only ready media can receive new usage.');
        }

        return DB::transaction(function () use ($actor, $asset, $ownerType, $ownerId, $fieldRole, $alt, $decorative, $order): MediaUsage {
            $usage = MediaUsage::query()->create(['id' => (string) Str::ulid(), 'media_asset_id' => $asset->id, 'owner_type' => $ownerType, 'owner_identifier' => $ownerId, 'field_role' => $fieldRole, 'alt_text_override' => $alt, 'decorative_override' => $decorative, 'sort_order' => $order]);
            $this->audit->handle('media.usage.attached', $usage, $actor, null, ['asset_id' => $asset->id, 'owner_type' => $ownerType, 'field_role' => $fieldRole], PermissionRegistry::MEDIA_EDIT);

            return $usage;
        });
    }
}
