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
use Illuminate\Support\Str;
use InvalidArgumentException;

final class AssignProductBadge
{
    public function __construct(private ProductBadgeRegistry $registry, private ProductBadgeSetFingerprint $states, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Product $product, string $expected, string $badgeKey): ProductBadge
    {
        $definition = $this->registry->assignable($badgeKey);

        return DB::transaction(function () use ($actor, $product, $expected, $badgeKey, $definition): ProductBadge {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            if (! hash_equals($expected, $this->states->for($locked))) {
                throw new StaleCatalogueState;
            }
            if ($locked->archived_at !== null) {
                throw new InvalidArgumentException('Archived Products cannot receive badges.');
            }
            $active = ProductBadge::query()->active()->where('product_id', $locked->id)->lockForUpdate()->get();
            if ($active->contains('badge_key', $badgeKey)) {
                throw new InvalidArgumentException('This Product badge is already assigned.');
            }
            if ($active->count() >= $definition['maximum']) {
                throw new InvalidArgumentException('The Product badge limit has been reached.');
            }
            $id = (string) Str::ulid();
            $position = $active->count();
            $badge = ProductBadge::query()->create([
                'id' => $id, 'product_id' => $locked->id, 'badge_key' => $badgeKey, 'position' => $position,
                'active_key' => ActiveOrderingKeys::active($locked->id, $badgeKey),
                'position_key' => ActiveOrderingKeys::active($locked->id, (string) $position),
            ]);
            $locked->increment('lock_version');
            $this->audit->handle('product.badge.assigned', $locked, $actor, null, ['badge_id' => $id, 'badge_key' => $badgeKey, 'position' => $position]);

            return $badge;
        }, 3);
    }
}
