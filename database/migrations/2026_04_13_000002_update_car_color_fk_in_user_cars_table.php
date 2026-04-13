<?php

use App\Models\CarColor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_cars', static function (Blueprint $table) {
            // Drop the old FK referencing car_colors
            $table->dropForeign(['car_color_id']);
        });

        // Null out existing values since IDs from car_colors won't match product_option_items
        \Illuminate\Support\Facades\DB::table('user_cars')->update(['car_color_id' => null]);

        Schema::table('user_cars', static function (Blueprint $table) {
            // Add new FK referencing product_option_items
            $table->foreign('car_color_id')->references('id')->on('product_option_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_cars', static function (Blueprint $table) {
            $table->dropForeign(['car_color_id']);
            $table->foreign('car_color_id')->references('id')->on('car_colors')->nullOnDelete();
        });
    }
};
