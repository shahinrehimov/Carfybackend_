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
        Schema::table('product_categories', static function (Blueprint $table) {
            $table->unique(['product_id', 'category_id', 'is_deleted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_categories', static function (Blueprint $table) {
            $table->dropUnique(['product_id', 'category_id', 'is_deleted']);
        });
    }
};
