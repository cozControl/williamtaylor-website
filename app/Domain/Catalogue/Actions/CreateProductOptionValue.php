<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Models\ProductOption;
use App\Domain\Catalogue\Models\ProductOptionValue;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateProductOptionValue
{
    public function __construct(private RecordAuditEvent $audit) {}

    public function handle(User $actor, ProductOption $option, string $key, string $label, int $position): ProductOptionValue
    {
        return DB::transaction(function () use ($actor, $option, $key, $label, $position) {
            $o = ProductOption::with('product')->lockForUpdate()->findOrFail($option->id);
            if ($o->archived_at || $o->product->archived_at) {
                throw new InvalidArgumentException('Archived options cannot receive values.');
            }$v = ProductOptionValue::create(['product_option_id' => $o->id, 'key' => Str::slug($key, '_'), 'label' => trim($label), 'position' => $position]);
            $o->product->increment('lock_version');
            $this->audit->handle('product.option-value.created', $o->product, $actor, null, ['option_id' => $o->id, 'value_id' => $v->id]);

            return $v;
        }, 3);
    }
}
