<?php

use App\Models\Product;
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
        Schema::create('baskets', static function (Blueprint $table) {
            $table->id();
            $table->morphs('userable');
            $table->foreignIdFor(Product::class)->constrained()->cascadeOnDelete();
            $table->boolean('is_set')->default(false);
            $table->unsignedInteger('quantity');
            $table->json('sets')->nullable();
            $table->timestamps();
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
            $table->nullableMorphs('deletable');
            $table->string('is_deleted', 30)->default('false');
            $table->softDeletes();

            $table->unique(['userable_type', 'userable_id', 'product_id', 'is_deleted'], 'baskets_user_product_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('baskets');
    }
};
