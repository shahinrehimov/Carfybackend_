<?php

use App\Enum\ProductVariantApprovalStatusEnum;
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
        Schema::create('product_variant_approval_statuses', static function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ProductVariant::class)->constrained()->onDelete('cascade');
            $table->string('approval_status')->default(ProductVariantApprovalStatusEnum::PENDING->value)->comment('ProductVariantApprovalStatusEnum');
            $table->string('approval_status_message', 3000)->nullable();
            $table->timestamps();
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
            $table->nullableMorphs('deletable');
            $table->string('is_deleted', 30)->default('false');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variant_approval_statuses');
    }
};
