<?php

use App\Models\ProductVariant;
use App\Models\Wishlist;
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
        Wishlist::query()->truncate();

        Schema::table('wishlists', static function (Blueprint $table) {
            $table->foreignIdFor(ProductVariant::class)->constrained()->cascadeOnDelete();

            $table->dropUnique('wishlists_user_product_unique');

            $table->unique(['userable_type', 'userable_id', 'product_variant_id', 'is_deleted'], 'wishlists_user_product_variant_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Wishlist::query()->truncate();

        Schema::table('wishlists', static function (Blueprint $table) {
            $table->dropUnique('wishlists_user_product_variant_unique');

            $table->unique(['userable_type', 'userable_id', 'product_id', 'is_deleted'], 'wishlists_user_product_unique');

            $table->dropConstrainedForeignIdFor(ProductVariant::class);
        });
    }
};
