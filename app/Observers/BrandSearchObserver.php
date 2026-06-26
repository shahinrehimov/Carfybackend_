<?php

namespace App\Observers;

use App\Models\Brand;
use App\Models\Product;
use App\Services\ProductSearchService;

/**
 * Brand names are denormalised into each listing's document, so a brand rename
 * requires re-indexing all of its products' listings. Brand edits are rare.
 */
class BrandSearchObserver
{
    public function saved(Brand $brand): void
    {
        $productIds = Product::query()->where('brand_id', $brand->id)->pluck('id')->all();

        ProductSearchService::instance()->reindexForProductIds($productIds);
    }
}
