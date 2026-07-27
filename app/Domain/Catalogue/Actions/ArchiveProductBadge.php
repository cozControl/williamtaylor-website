<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductBadge;
use App\Domain\Catalogue\Support\ArchiveReason;
use App\Domain\Catalogue\Support\ProductBadgeSetFingerprint;
use App\Domain\Merchandising\Support\ActiveOrderingKeys;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ArchiveProductBadge
{
    public function __construct(private ProductBadgeSetFingerprint $states, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Product $product, ProductBadge $badge, string $expected, string $reason): void
    {
        $reason = ArchiveReason::normalize($reason);
        DB::transaction(function () use ($actor, $product, $badge, $expected, $reason): void {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            if (! hash_equals($expected, $this->states->for($locked))) {
                throw new StaleCatalogueState;
            }
            $item = ProductBadge::query()->lockForUpdate()->findOrFail($badge->id);
            if ($item->product_id !== $locked->id) {
                throw new InvalidArgumentException('Product badge ownership mismatch.');
            }
            if ($item->archived_at !== null) {
                return;
            }
            $item->update([
                'archived_at' => now('UTC'), 'archived_by' => $actor->id, 'archive_reason' => $reason,
                'active_key' => ActiveOrderingKeys::archived($item->id),
                'position_key' => ActiveOrderingKeys::archived($item->id),
            ]);
            $this->compact($locked);
            $locked->increment('lock_version');
            $this->audit->handle('product.badge.archived', $locked, $actor, ['badge_id' => $item->id], ['state' => 'archived'], reason: $reason);
        }, 3);
    }

    private function compact(Product $product): void
    {
        $items = ProductBadge::query()->active()->where('product_id', $product->id)->orderBy('position')->lockForUpdate()->get();
        foreach ($items as $position => $item) {
            $item->update(['position' => $position, 'position_key' => ActiveOrderingKeys::active($product->id, (string) $position)]);
        }
    }
}
