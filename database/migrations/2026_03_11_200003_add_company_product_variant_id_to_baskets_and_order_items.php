<?php

use App\Models\Basket;
use App\Models\CompanyProductVariant;
use App\Models\Order;
use App\Models\OrderItem;
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
        OrderItem::query()->truncate();
        Order::query()->truncate();

        Schema::table('baskets', static function (Blueprint $table) {
            $table->foreignIdFor(CompanyProductVariant::class)
                ->after('product_variant_id')
                ->constrained('company_product_variant')
                ->cascadeOnDelete();

            $table->dropUnique(['userable_type', 'userable_id', 'product_variant_id', 'is_deleted']);
            $table->unique(['userable_type', 'userable_id', 'company_product_variant_id', 'is_deleted']);
        });

        Schema::table('order_items', static function (Blueprint $table) {
            $table->foreignIdFor(CompanyProductVariant::class)
                ->nullable()
                ->after('product_variant_id')
                ->constrained('company_product_variant')
                ->nullOnDelete();

            $table->dropUnique(['order_id', 'product_variant_id', 'is_deleted']);
            $table->unique(['order_id', 'company_product_variant_id', 'is_deleted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Basket::query()->truncate();
        OrderItem::query()->truncate();
        Order::query()->truncate();

        Schema::table('baskets', static function (Blueprint $table) {
            $table->dropUnique(['userable_type', 'userable_id', 'company_product_variant_id', 'is_deleted']);
            $table->dropConstrainedForeignIdFor(CompanyProductVariant::class);
            $table->unique(['userable_type', 'userable_id', 'product_variant_id', 'is_deleted']);
        });

        Schema::table('order_items', static function (Blueprint $table) {
            $table->dropUnique(['order_id', 'company_product_variant_id', 'is_deleted']);
            $table->dropConstrainedForeignIdFor(CompanyProductVariant::class);
            $table->unique(['order_id', 'product_variant_id', 'is_deleted']);
        });
    }
};
