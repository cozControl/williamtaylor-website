<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductOption;
use App\Domain\Catalogue\Support\ArchiveReason;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ArchiveProductOption
{
    public function __construct(private ProductStateFingerprint $states, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Product $product, ProductOption $option, string $expected, string $reason): void
    {
        $reason = ArchiveReason::normalize($reason);
        DB::transaction(function () use ($actor, $product, $option, $expected, $reason): void {
            $p = Product::query()->lockForUpdate()->findOrFail($product->id);
            $o = ProductOption::query()->lockForUpdate()->findOrFail($option->id);
            if ($o->product_id !== $p->id) {
                throw new InvalidArgumentException('Option does not belong to Product.');
            }
            if (! hash_equals($expected, $this->states->for($p))) {
                throw new StaleCatalogueState;
            }
            if ($p->archived_at) {
                throw new InvalidArgumentException('Archived Products are read-only.');
            }
            if ($o->archived_at !== null) {
                return;
            }
            $o->forceFill(['archived_at' => now('UTC')])->save();
            $p->forceFill(['catalogue_status' => 'draft', 'lock_version' => $p->lock_version + 1])->save();
            $this->audit->handle('product.option.archived', $p, $actor, null, ['option_id' => $o->id], reason: $reason);
        }, 3);
    }
}
