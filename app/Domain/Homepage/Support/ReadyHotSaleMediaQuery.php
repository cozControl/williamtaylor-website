<?php

namespace App\Domain\Homepage\Support;

use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ReadyHotSaleMediaQuery
{
    /** @return LengthAwarePaginator<int, MediaAsset> */
    public function paginate(string $search = ''): LengthAwarePaginator
    {
        return $this->eligible()->when($search !== '', fn (Builder $query): Builder => $query->where(fn (Builder $nested): Builder => $nested
            ->where('internal_title', 'like', "%{$search}%")
            ->orWhere('original_filename', 'like', "%{$search}%")
            ->orWhere('default_alt_text', 'like', "%{$search}%")))
            ->orderByDesc('created_at')->orderBy('id')->paginate(24);
    }

    public function findEligible(string $id): ?MediaAsset
    {
        return $this->eligible()->whereKey($id)->first();
    }

    /** @return Builder<MediaAsset> */
    private function eligible(): Builder
    {
        return MediaAsset::query()->where('state', MediaAssetState::Ready)
            ->whereIn('resource_type', [MediaResourceType::Image, MediaResourceType::Video])
            ->whereNotNull('confirmed_at')->whereNull('archived_at');
    }
}
