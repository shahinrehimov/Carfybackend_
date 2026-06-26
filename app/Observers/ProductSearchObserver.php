<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\ProductSearchService;

/**
 * Keeps Elasticsearch in sync when a product's denormalised content changes.
 * Translation edits go through Translatable::saveLang(), which touches the
 * product, so "saved" also covers title/description changes.
 */
class ProductSearchObserver
{
    public function saved(Product $product): void
    {
        ProductSearchService::instance()->reindexForProductIds([$product->id]);
    }

    public function deleted(Product $product): void
    {
        ProductSearchService::instance()->reindexForProductIds([$product->id]);
    }

    public function restored(Product $product): void
    {
        ProductSearchService::instance()->reindexForProductIds([$product->id]);
    }
}
