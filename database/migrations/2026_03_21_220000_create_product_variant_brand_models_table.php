<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_brand_models', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('brand_model_id')->constrained('brand_models')->cascadeOnDelete();
            $table->primary(['product_variant_id', 'brand_model_id']);
        });

        // Migrate existing single brand_model_id to the pivot table
        DB::statement('
            INSERT INTO product_variant_brand_models (product_variant_id, brand_model_id)
            SELECT id, brand_model_id
            FROM product_variants
            WHERE brand_model_id IS NOT NULL
              AND deleted_at IS NULL
        ');

        // Drop the old column
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropForeign(['brand_model_id']);
            $table->dropColumn('brand_model_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreignId('brand_model_id')->nullable()->constrained('brand_models')->nullOnDelete();
        });

        // Restore first brand_model from pivot (best-effort)
        DB::statement('
            UPDATE product_variants pv
            SET brand_model_id = (
                SELECT brand_model_id
                FROM product_variant_brand_models pvbm
                WHERE pvbm.product_variant_id = pv.id
                LIMIT 1
            )
        ');

        Schema::dropIfExists('product_variant_brand_models');
    }
};
