<?php

namespace App\Domain\Merchandising\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Support\ArchiveReason;
use App\Domain\Merchandising\Exceptions\StaleMerchandisingState;
use App\Domain\Merchandising\Models\ProductPlacement;
use App\Domain\Merchandising\Support\ActiveOrderingKeys;
use App\Domain\Merchandising\Support\ProductPlacementSetFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ArchiveProductPlacement
{
    public function __construct(private ProductPlacementSetFingerprint $states, private RecordAuditEvent $audit) {}

    public function handle(User $actor, string $slot, ProductPlacement $placement, string $expected, string $reason): void
    {
        $reason = ArchiveReason::normalize($reason);
        DB::transaction(function () use ($actor, $slot, $placement, $expected, $reason): void {
            $item = ProductPlacement::query()->lockForUpdate()->findOrFail($placement->id);
            if ($item->slot_key !== $slot) {
                throw new InvalidArgumentException('Product placement slot mismatch.');
            }
            if (! hash_equals($expected, $this->states->for($slot))) {
                throw new StaleMerchandisingState;
            }
            if ($item->archived_at !== null) {
                return;
            }
            $item->update([
                'archived_at' => now('UTC'), 'archived_by' => $actor->id, 'archive_reason' => $reason,
                'active_key' => ActiveOrderingKeys::archived($item->id), 'position_key' => ActiveOrderingKeys::archived($item->id),
            ]);
            $this->compact($slot);
            $this->audit->handle('product.placement.archived', $item, $actor, null, ['state' => 'archived'], reason: $reason);
        }, 3);
    }

    private function compact(string $slot): void
    {
        $items = ProductPlacement::query()->active()->where('slot_key', $slot)->orderBy('position')->lockForUpdate()->get();
        foreach ($items as $position => $item) {
            $item->update(['position' => $position, 'position_key' => ActiveOrderingKeys::active($slot, (string) $position)]);
        }
    }
}
