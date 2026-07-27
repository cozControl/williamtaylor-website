<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductRevision;
use App\Domain\Catalogue\Support\ProductContentSchema;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateProductRevision
{
    public function __construct(private ProductContentSchema $schema, private RecordAuditEvent $audit) {}

    /** @param array<string, mixed> $content */
    public function handle(User $actor, Product $product, int $expectedLock, array $content): ProductRevision
    {
        return DB::transaction(function () use ($actor, $product, $expectedLock, $content) {
            $p = Product::query()->lockForUpdate()->findOrFail($product->id);
            if ($p->lock_version !== $expectedLock) {
                throw new StaleCatalogueState;
            }if ($p->archived_at) {
                throw new InvalidArgumentException('Archived Products are read-only.');
            }$n = $this->schema->normalize($content);
            $revision = ProductRevision::create([...$n, 'product_id' => $p->id, 'revision_number' => (int) ProductRevision::where('product_id', $p->id)->max('revision_number') + 1, 'checksum' => $this->schema->checksum($n), 'created_by' => $actor->id, 'created_at' => now('UTC')]);
            $p->forceFill(['current_draft_revision_id' => $revision->id, 'lock_version' => $p->lock_version + 1])->save();
            $this->audit->handle('product.revision.created', $p, $actor, null, ['revision_id' => $revision->id, 'revision_number' => $revision->revision_number, 'checksum' => $revision->checksum]);

            return $revision;
        }, 3);
    }
}
