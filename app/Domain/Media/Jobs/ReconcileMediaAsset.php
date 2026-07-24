<?php

namespace App\Domain\Media\Jobs;

use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ReconcileMediaAsset implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $assetId) {}

    public function handle(MediaProvider $provider): void
    {
        $asset = MediaAsset::query()->find($this->assetId);
        if (! $asset || $asset->state === MediaAssetState::Archived) {
            return;
        }
        if (! $provider->assetExists($asset->provider_asset_id)) {
            $asset->update(['state' => MediaAssetState::Failed, 'processing_error' => 'Provider asset could not be verified.']);
        }
    }
}
