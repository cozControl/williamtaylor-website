<?php

namespace App\Domain\Media\Queries;

use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ReadyImagePickerQuery
{
    public const PAGE_SIZE = 24;

    /** @return LengthAwarePaginator<int, MediaAsset> */
    public function paginate(string $search = ''): LengthAwarePaginator
    {
        return MediaAsset::query()
            ->where('state', MediaAssetState::Ready)
            ->where('resource_type', MediaResourceType::Image)
            ->whereNotNull('confirmed_at')
            ->whereNull('archived_at')
            ->when($search !== '', fn (Builder $query): Builder => $query->where(fn (Builder $nested): Builder => $nested
                ->where('internal_title', 'like', "%{$search}%")
                ->orWhere('original_filename', 'like', "%{$search}%")
                ->orWhere('default_alt_text', 'like', "%{$search}%")))
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->paginate(self::PAGE_SIZE);
    }

    public function findEligible(string $id): ?MediaAsset
    {
        return MediaAsset::query()
            ->whereKey($id)
            ->where('state', MediaAssetState::Ready)
            ->where('resource_type', MediaResourceType::Image)
            ->whereNotNull('confirmed_at')
            ->whereNull('archived_at')
            ->first();
    }
}
