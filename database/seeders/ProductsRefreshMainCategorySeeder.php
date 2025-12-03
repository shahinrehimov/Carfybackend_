<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductsRefreshMainCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $products = Product::withTrashed()
            ->with([
                'categoriesRel' => function ($query) {
                    $query->withTrashed();
                    $query->orderBy('id');
                },
            ])
            ->orderBy('id')
            ->get();

        $mainCategory = Category::query()->onlySubs()->orderBy('id')->first();

        foreach ($products as $product) {
            /** @var Product $product */
            if (!$product->trashed()) {
                $category = $product->categoriesRel->whereNull('deleted_at')->first();
            } else {
                $category = $product->categoriesRel?->first();
            }

            $mainCategoryId = $category?->category_id ?? ($mainCategory?->id ?? null);

            if ($product->main_category_id !== $mainCategoryId) {
                $product->update([
                    'main_category_id' => $mainCategoryId,
                ]);
            }
        }
    }
}
