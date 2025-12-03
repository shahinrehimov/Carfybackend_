<?php

use App\Models\Basket;
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
        Basket::query()->truncate();

        Schema::table('baskets', static function (Blueprint $table) {
            $table->foreignIdFor(ProductVariant::class)->constrained()->cascadeOnDelete();

            $table->dropUnique('baskets_user_product_unique');

            $table->unique(['userable_type', 'userable_id', 'product_variant_id', 'is_deleted'], 'baskets_user_product_variant_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Basket::query()->truncate();

        Schema::table('baskets', static function (Blueprint $table) {
            $table->dropUnique('baskets_user_product_variant_unique');

            $table->unique(['userable_type', 'userable_id', 'product_id', 'is_deleted'], 'baskets_user_product_unique');

            $table->dropConstrainedForeignIdFor(ProductVariant::class);
        });
    }
};
