<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use Illuminate\Console\Command;

class DeleteProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete products and categories';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $variants = ProductVariant::query()->get();

        foreach ($variants as $variant) {
            $variant->delete();
        }

        $products = Product::query()->get();

        foreach ($products as $product) {
            $product->delete();
        }

        $categories = Category::query()->get();

        foreach ($categories as $category) {
            $category->delete();
        }

        $brands = Brand::query()->get();

        foreach ($brands as $brand) {
            $brand->delete();
        }

        $colorOption = ProductOption::query()->where('name', 'color')->first();

        if ($colorOption) {
            $optionItems = $colorOption->items()->get();

            foreach ($optionItems as $optionItem) {
                $optionItem->delete();
            }
        }
    }
}
