<?php

use App\Enum\ProductVariantApprovalStatusEnum;
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
        Schema::create('company_product_variant', static function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignIdFor(\App\Models\Company::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\ProductVariant::class)->constrained()->cascadeOnDelete();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('price')->default(0);
            $table->string('discount_type', 30)->nullable();
            $table->unsignedMediumInteger('discount')->default(0);
            $table->dateTime('discount_start_at')->nullable();
            $table->dateTime('discount_end_at')->nullable();
            $table->foreignIdFor(\App\Models\City::class)->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('approval_status', 30)->default(ProductVariantApprovalStatusEnum::PENDING->value);
            $table->string('approval_status_message', 3000)->nullable();
            $table->timestamps();
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
            $table->nullableMorphs('deletable');
            $table->string('is_deleted')->default('false');
            $table->softDeletes();

            $table->unique(['company_id', 'product_variant_id', 'is_deleted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_product_variant');
    }
};
