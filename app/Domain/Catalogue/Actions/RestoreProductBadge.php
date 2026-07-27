<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductBadge;
use App\Domain\Catalogue\Support\ProductBadgeRegistry;
use App\Domain\Catalogue\Support\ProductBadgeSetFingerprint;
use App\Domain\Merchandising\Support\ActiveOrderingKeys;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RestoreProductBadge
{
    public function __construct(private ProductBadgeRegistry $registry, private ProductBadgeSetFingerprint $states, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Product $product, ProductBadge $badge, string $expected): void
    {
        DB::transaction(function () use ($actor, $product, $badge, $expected): void {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            if (! hash_equals($expected, $this->states->for($locked))) {
                throw new StaleCatalogueState;
            }
            $item = ProductBadge::query()->lockForUpdate()->findOrFail($badge->id);
            if ($item->product_id !== $locked->id) {
                throw new InvalidArgumentException('Product badge ownership mismatch.');
            }
            if ($item->archived_at === null) {
                return;
            }
            if ($locked->archived_at !== null) {
                throw new InvalidArgumentException('Archived Products cannot restore badges.');
            }
            $definition = $this->registry->assignable($item->badge_key);
            $active = ProductBadge::query()->active()->where('product_id', $locked->id)->lockForUpdate()->get();
            if ($active->contains('badge_key', $item->badge_key) || $active->count() >= $definition['maximum']) {
                throw new InvalidArgumentException('Product badge cannot be restored.');
            }
            $position = $active->count();
            $item->update([
                'position' => $position, 'archived_at' => null, 'archived_by' => null, 'archive_reason' => null,
                'active_key' => ActiveOrderingKeys::active($locked->id, $item->badge_key),
                'position_key' => ActiveOrderingKeys::active($locked->id, (string) $position),
            ]);
            $locked->increment('lock_version');
            $this->audit->handle('product.badge.restored', $locked, $actor, ['state' => 'archived'], ['badge_id' => $item->id, 'position' => $position]);
        }, 3);
    }
}
