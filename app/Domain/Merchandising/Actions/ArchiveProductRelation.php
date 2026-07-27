<?php

namespace App\Domain\Merchandising\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Support\ArchiveReason;
use App\Domain\Merchandising\Exceptions\StaleMerchandisingState;
use App\Domain\Merchandising\Models\ProductRelation;
use App\Domain\Merchandising\Support\ActiveOrderingKeys;
use App\Domain\Merchandising\Support\ProductRelationSetFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ArchiveProductRelation
{
    public function __construct(private ProductRelationSetFingerprint $states, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Product $source, ProductRelation $relation, string $expected, string $reason): void
    {
        $reason = ArchiveReason::normalize($reason);
        DB::transaction(function () use ($actor, $source, $relation, $expected, $reason): void {
            $locked = Product::query()->lockForUpdate()->findOrFail($source->id);
            $item = ProductRelation::query()->lockForUpdate()->findOrFail($relation->id);
            if ($item->source_product_id !== $locked->id) {
                throw new InvalidArgumentException('Product relation ownership mismatch.');
            }
            if (! hash_equals($expected, $this->states->for($locked, $item->relation_kind))) {
                throw new StaleMerchandisingState;
            }
            if ($item->archived_at !== null) {
                return;
            }
            $item->update([
                'archived_at' => now('UTC'), 'archived_by' => $actor->id, 'archive_reason' => $reason,
                'active_key' => ActiveOrderingKeys::archived($item->id), 'position_key' => ActiveOrderingKeys::archived($item->id),
            ]);
            $this->compact($locked, $item->relation_kind);
            $this->audit->handle('product.relation.archived', $item, $actor, null, ['state' => 'archived'], reason: $reason);
        }, 3);
    }

    private function compact(Product $source, string $kind): void
    {
        $items = ProductRelation::query()->active()->where('source_product_id', $source->id)
            ->where('relation_kind', $kind)->orderBy('position')->lockForUpdate()->get();
        foreach ($items as $position => $item) {
            $item->update(['position' => $position, 'position_key' => ActiveOrderingKeys::active($source->id, $kind, (string) $position)]);
        }
    }
}
