<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Support\ProductTypeRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateProduct
{
    public function __construct(private ProductTypeRegistry $types, private RecordAuditEvent $audit) {}

    public function handle(User $actor, string $stableKey, string $slug, string $type = 'apparel'): Product
    {
        $stableKey = Str::slug($stableKey);
        $slug = Str::slug($slug);
        if (strlen($stableKey) < 3 || strlen($slug) < 3) {
            throw new InvalidArgumentException('Stable key and slug must be normalized values of at least three characters.');
        }$this->types->get($type);

        return DB::transaction(function () use ($actor, $stableKey, $slug, $type) {
            $p = Product::create(['stable_key' => $stableKey, 'slug' => $slug, 'product_type' => $type, 'catalogue_status' => 'draft', 'lock_version' => 0, 'created_by' => $actor->id]);
            $this->audit->handle('product.created', $p, $actor, null, ['product_type' => $type, 'status' => 'draft']);

            return $p;
        }, 3);
    }
}
