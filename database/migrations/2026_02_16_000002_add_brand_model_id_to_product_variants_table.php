<?php

use App\Models\Brand;
use App\Models\BrandModel;
use App\Models\ProductVariant;
use App\Services\LangService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_variants', static function (Blueprint $table) {
            $table->foreignIdFor(BrandModel::class)->nullable()->constrained()->cascadeOnDelete();
        });

        $data = [];
        foreach (LangService::instance()->getLanguages() as $lang) {
            $data[] = [
                'title' => 'Unknown',
                'language_id' => $lang['id'],
            ];
        }

        $model = BrandModel::query()->first();

        if (!$model) {
            $brand = Brand::query()->first();

            if (!$brand) {
                $brand = Brand::createWithLangs([
                    'translations' => $data,
                ]);
            }

            $model = BrandModel::createWithLangs([
                'brand_id' => $brand->id,
                'translations' => $data,
            ]);
        }

        ProductVariant::withTrashed()->update(['brand_model_id' => $model->id]);

        Schema::table('product_variants', static function (Blueprint $table) {
            $table->unsignedBigInteger('brand_model_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', static function (Blueprint $table) {
            $table->dropForeignIdFor(BrandModel::class);
        });
    }
};
