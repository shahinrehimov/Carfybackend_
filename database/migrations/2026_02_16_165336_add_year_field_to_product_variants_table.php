<?php

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
        Schema::table('product_variants', static function (Blueprint $table) {
            $table->unsignedInteger('year')->nullable();
        });

        ProductVariant::withTrashed()->update(['year' => now()->year]);

        Schema::table('product_variants', static function (Blueprint $table) {
            $table->unsignedInteger('year')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', static function (Blueprint $table) {
            $table->dropColumn('year');
        });
    }
};
