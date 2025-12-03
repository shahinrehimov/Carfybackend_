<?php

use App\Models\GroupProduct;
use App\Models\ProductVariant;
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
        GroupProduct::query()->truncate();

        Schema::table('group_products', static function (Blueprint $table) {
            $table->foreignIdFor(ProductVariant::class)->constrained()->cascadeOnDelete();

            $table->dropUnique(['group_id', 'product_id', 'is_deleted']);

            $table->unique(['group_id', 'product_variant_id', 'is_deleted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        GroupProduct::query()->truncate();

        Schema::table('group_products', static function (Blueprint $table) {
            $table->dropUnique(['group_id', 'product_variant_id', 'is_deleted']);

            $table->unique(['group_id', 'product_id', 'is_deleted']);

            $table->dropConstrainedForeignIdFor(ProductVariant::class);
        });
    }
};
