<?php

use App\Models\Order;
use App\Models\OrderItem;
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
        Order::query()->truncate();
        OrderItem::query()->truncate();

        Schema::table('order_items', static function (Blueprint $table) {
            $table->foreignIdFor(ProductVariant::class)->constrained()->cascadeOnDelete();

            $table->dropUnique('order_items_order_product_unique');

            $table->unique(['order_id', 'product_variant_id', 'is_deleted'], 'order_items_order_product_variant_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Order::query()->truncate();
        OrderItem::query()->truncate();

        Schema::table('order_items', static function (Blueprint $table) {
            $table->dropUnique('order_items_order_product_variant_unique');

            $table->unique(['order_id', 'product_id', 'is_deleted'], 'order_items_order_product_unique');

            $table->dropConstrainedForeignIdFor(ProductVariant::class);
        });
    }
};
