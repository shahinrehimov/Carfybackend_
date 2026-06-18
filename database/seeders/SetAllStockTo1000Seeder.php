<?php

namespace Database\Seeders;

use App\Models\CompanyProductVariant;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class SetAllStockTo1000Seeder extends Seeder
{
    public function run(): void
    {
        ProductVariant::query()->update(['stock' => 1000]);

        CompanyProductVariant::query()->update(['stock' => 1000]);

        $variantCount = ProductVariant::query()->count();
        $cpvCount = CompanyProductVariant::query()->count();

        $this->command->info("Updated {$variantCount} product variants and {$cpvCount} seller listings to stock=1000.");
    }
}
