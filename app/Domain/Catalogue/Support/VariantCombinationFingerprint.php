<?php

namespace App\Domain\Catalogue\Support;

use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductOption;
use App\Domain\Catalogue\Models\ProductOptionValue;
use InvalidArgumentException;

final class VariantCombinationFingerprint
{
    /** @param list<array{option_id: string, value_id: string}> $pairs */
    public function for(Product $product, array $pairs, bool $complete = true): string
    {
        if ($pairs === [] && ($complete === false || $product->options()->active()->exists())) {
            throw new InvalidArgumentException('A Variant combination cannot be empty when active options exist.');
        }$normalized = [];
        foreach ($pairs as $pair) {
            $option = ProductOption::query()->findOrFail($pair['option_id']);
            $value = ProductOptionValue::query()->findOrFail($pair['value_id']);
            if ($option->product_id !== $product->id || $value->product_option_id !== $option->id || $option->archived_at || $value->archived_at) {
                throw new InvalidArgumentException('Variant option/value ownership or state is invalid.');
            }if (isset($normalized[$option->id])) {
                throw new InvalidArgumentException('A Variant may select only one value per option.');
            }$normalized[$option->id] = $value->id;
        }ksort($normalized, SORT_STRING);
        if ($complete && count($normalized) !== $product->options()->active()->count()) {
            throw new InvalidArgumentException('Variant combination must cover every active option.');
        }

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }
}
