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
        Schema::table('products', static function (Blueprint $table) {
            $table->dropColumn('stock');
            $table->dropColumn('price');
            $table->dropColumn('discount_type');
            $table->dropColumn('discount');
            $table->dropColumn('discount_start_at');
            $table->dropColumn('discount_end_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', static function (Blueprint $table) {
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('price')->nullable()->comment('in cents, null for sets');
            $table->string('discount_type', 30)->nullable()->comment('DiscountTypeEnum');
            $table->unsignedMediumInteger('discount')->default(0);
            $table->dateTime('discount_start_at')->nullable();
            $table->dateTime('discount_end_at')->nullable();
        });
    }
};
