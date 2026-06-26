<?php

namespace App\Observers;

use App\Models\Category;
use App\Models\Product;
use App\Services\ProductSearchService;

/**
 * Category names are denormalised into each listing's document, so a category
 * rename requires re-indexing listings of products in that category (as main
 * category or via the product_categories pivot). Category edits are rare.
 */
class CategorySearchObserver
{
    public function saved(Category $category): void
    {
        $productIds = Product::query()
            ->where('main_category_id', $category->id)
            ->orWhereHas('categories', fn ($query) => $query->where('categories.id', $category->id))
            ->pluck('id')
            ->all();

        ProductSearchService::instance()->reindexForProductIds($productIds);
    }
}
