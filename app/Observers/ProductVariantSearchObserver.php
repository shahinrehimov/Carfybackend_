<?php

namespace App\Observers;

use App\Models\ProductVariant;
use App\Services\ProductSearchService;

/**
 * Re-indexes a variant's listings when variant-level searchable data changes
 * (oem_code, is_active). The listings themselves auto-sync via the Searchable
 * trait on their own saves.
 */
class ProductVariantSearchObserver
{
    public function saved(ProductVariant $variant): void
    {
        $this->reindex($variant);
    }

    public function deleted(ProductVariant $variant): void
    {
        $this->reindex($variant);
    }

    public function restored(ProductVariant $variant): void
    {
        $this->reindex($variant);
    }

    private function reindex(ProductVariant $variant): void
    {
        if ($variant->product_id) {
            ProductSearchService::instance()->reindexForProductIds([$variant->product_id]);
        }
    }
}
