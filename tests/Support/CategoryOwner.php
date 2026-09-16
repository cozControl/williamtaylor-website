<?php

namespace Tests\Support;

use App\Domain\Catalogue\Models\Collection;

/** Explicit, unpublished owner for legacy fixtures that do not exercise Collections. */
final class CategoryOwner
{
    public static function for(int $actorId): Collection
    {
        return Collection::query()->firstOrCreate(['slug' => 'fixture-categories'], ['collection_type' => 'curated', 'created_by' => $actorId]);
    }
}
