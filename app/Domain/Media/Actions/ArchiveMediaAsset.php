<?php

namespace App\Domain\Media\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\PublicProjection\Services\InvalidatePublicPagesUsingMedia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ArchiveMediaAsset
{
    public function __construct(private RecordAuditEvent $audit) {}

    public function handle(User $actor, MediaAsset $asset, string $reason): void
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::MEDIA_ARCHIVE);
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Archive reason is required.');
        }
        DB::transaction(function () use ($actor, $asset, $reason): void {
            $before = $asset->state->value;
            $asset->update(['state' => MediaAssetState::Archived, 'archived_at' => now()]);
            $this->audit->handle('media.asset.archived', $asset, $actor, ['state' => $before], ['state' => 'archived', 'usage_count' => $asset->usages()->count()], PermissionRegistry::MEDIA_ARCHIVE, $reason);
        });
        DB::afterCommit(fn () => app(InvalidatePublicPagesUsingMedia::class)->handle($asset));
    }
}
