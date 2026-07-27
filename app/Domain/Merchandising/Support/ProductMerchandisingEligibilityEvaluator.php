<?php

namespace App\Domain\Merchandising\Support;

use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Support\CatalogueReadinessEvaluator;
use App\Domain\Merchandising\Data\MerchandisingEligibilityResult;

final class ProductMerchandisingEligibilityEvaluator
{
    public function __construct(private CatalogueReadinessEvaluator $catalogue) {}

    public function evaluate(Product $product): MerchandisingEligibilityResult
    {
        $failures = [];
        if ($product->archived_at !== null) {
            $failures[] = 'product_archived';
        }
        if (! $this->catalogue->evaluate($product)->ready) {
            $failures[] = 'catalogue_not_ready';
        }

        return new MerchandisingEligibilityResult($failures === [], $failures);
    }
}
