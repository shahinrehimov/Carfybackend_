<?php

use App\Models\ProductReview;
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
        ProductReview::query()->truncate();

        Schema::table('product_reviews', static function (Blueprint $table) {
            $table->foreignIdFor(ProductVariant::class)->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        ProductReview::query()->truncate();

        Schema::table('product_reviews', static function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(ProductVariant::class);
        });
    }
};
