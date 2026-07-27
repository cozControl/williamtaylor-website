<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductOption;
use App\Domain\Catalogue\Support\ProductTypeRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateProductOption
{
    public function __construct(private ProductTypeRegistry $types, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Product $product, int $expectedLock, string $key, string $label, int $position): ProductOption
    {
        return DB::transaction(function () use ($actor, $product, $expectedLock, $key, $label, $position) {
            $p = Product::lockForUpdate()->findOrFail($product->id);
            if ($p->lock_version !== $expectedLock) {
                throw new StaleCatalogueState;
            }$def = $this->types->get($p->product_type);
            $key = Str::slug($key, '_');
            if (! in_array($key, $def['option_keys'], true)) {
                throw new InvalidArgumentException('Unsupported Product option.');
            }if ($p->options()->whereNull('archived_at')->count() >= $def['maximum_options']) {
                throw new InvalidArgumentException('Maximum active Product options reached.');
            }$o = ProductOption::create(['product_id' => $p->id, 'key' => $key, 'label' => trim($label), 'position' => $position]);
            $p->increment('lock_version');
            $this->audit->handle('product.option.created', $p, $actor, null, ['option_id' => $o->id, 'key' => $key]);

            return $o;
        }, 3);
    }
}
