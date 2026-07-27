<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Support\ArchiveReason;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ArchiveProduct
{
    public function __construct(
        private ProductStateFingerprint $states,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(User $actor, Product $product, string $expected, string $reason): void
    {
        $reason = ArchiveReason::normalize($reason);

        DB::transaction(function () use ($actor, $product, $expected, $reason): void {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);

            if (! hash_equals($expected, $this->states->for($locked))) {
                throw new StaleCatalogueState;
            }

            if ($locked->archived_at !== null) {
                return;
            }

            $beforeStatus = $locked->catalogue_status;
            $locked->forceFill([
                'archived_at' => now('UTC'),
                'archived_by' => $actor->id,
                'archive_reason' => $reason,
                'catalogue_status' => 'draft',
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->audit->handle(
                'product.archived',
                $locked,
                $actor,
                ['status' => $beforeStatus],
                ['status' => 'draft'],
                reason: $reason,
            );
        }, 3);
    }
}
