<?php

use App\Models\CompanyProductVariant;
use App\Models\ProductReview;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        ProductReview::query()->truncate();

        Schema::table('product_reviews', static function (Blueprint $table) {
            $table->foreignIdFor(CompanyProductVariant::class)
                ->after('product_variant_id')
                ->constrained('company_product_variant')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        ProductReview::query()->truncate();

        Schema::table('product_reviews', static function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(CompanyProductVariant::class);
        });
    }
};
