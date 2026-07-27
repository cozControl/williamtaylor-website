<?php

namespace App\Domain\Media\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\PublicProjection\Services\InvalidatePublicPagesUsingMedia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class RestoreMediaAsset
{
    public function __construct(private MediaProvider $provider, private RecordAuditEvent $audit) {}

    public function handle(User $actor, MediaAsset $asset): void
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::MEDIA_RESTORE);
        if (! $this->provider->assetExists($asset->provider_asset_id)) {
            throw new RuntimeException('Provider asset could not be verified.');
        }
        DB::transaction(function () use ($actor, $asset): void {
            $asset->update(['state' => MediaAssetState::Ready, 'archived_at' => null]);
            $this->audit->handle('media.asset.restored', $asset, $actor, ['state' => 'archived'], ['state' => 'ready'], PermissionRegistry::MEDIA_RESTORE);
        });
        DB::afterCommit(fn () => app(InvalidatePublicPagesUsingMedia::class)->handle($asset));
    }
}
