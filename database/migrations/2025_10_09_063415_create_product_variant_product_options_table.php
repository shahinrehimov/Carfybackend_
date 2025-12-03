<?php

use App\Models\ProductOption;
use App\Models\ProductOptionItem;
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
        Schema::create('product_variant_product_options', static function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ProductVariant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ProductOption::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ProductOptionItem::class)->nullable()->comment('for hasItem is true')->constrained()->nullOnDelete();
            $table->string('value')->nullable()->comment('for hasItem is false and translatable is false');
            $table->string('slug', 10000);
            $table->timestamps();
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
            $table->nullableMorphs('deletable');
            $table->string('is_deleted', 30)->default('false');
            $table->softDeletes();

            $table->unique(['product_variant_id', 'product_option_id', 'is_deleted'], 'variant_option_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variant_product_options');
    }
};
