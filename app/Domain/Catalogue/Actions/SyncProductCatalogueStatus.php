<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Support\CatalogueReadinessEvaluator;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SyncProductCatalogueStatus
{
    public function __construct(private CatalogueReadinessEvaluator $readiness, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Product $product): string
    {
        return DB::transaction(function () use ($actor, $product) {
            $p = Product::lockForUpdate()->findOrFail($product->id);
            $next = $this->readiness->evaluate($p)->ready ? 'ready' : 'draft';
            if ($p->catalogue_status === $next) {
                return $next;
            }$before = $p->catalogue_status;
            $p->forceFill(['catalogue_status' => $next, 'lock_version' => $p->lock_version + 1])->save();
            $this->audit->handle('product.catalogue-status.changed', $p, $actor, ['status' => $before], ['status' => $next]);

            return $next;
        }, 3);
    }
}
