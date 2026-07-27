<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RestoreProduct
{
    public function __construct(
        private ProductStateFingerprint $states,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(User $actor, Product $product, string $expected): void
    {
        DB::transaction(function () use ($actor, $product, $expected): void {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);

            if (! hash_equals($expected, $this->states->for($locked))) {
                throw new StaleCatalogueState;
            }

            if ($locked->archived_at === null) {
                return;
            }

            $locked->forceFill([
                'archived_at' => null,
                'archived_by' => null,
                'archive_reason' => null,
                'catalogue_status' => 'draft',
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->audit->handle(
                'product.restored',
                $locked,
                $actor,
                ['state' => 'archived'],
                ['state' => 'active', 'status' => 'draft'],
            );
        }, 3);
    }
}
