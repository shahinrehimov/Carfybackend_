<?php

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
        Schema::table('product_variant_approval_statuses', static function (Blueprint $table) {
            $table->foreignIdFor(\App\Models\CompanyProductVariant::class)
                ->nullable()
                ->after('product_variant_id')
                ->constrained('company_product_variant')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variant_approval_statuses', static function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(\App\Models\CompanyProductVariant::class);
        });
    }
};
