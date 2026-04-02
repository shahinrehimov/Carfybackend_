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
            $table->foreignIdFor(CarColor::class)->nullable()->after('transmission_type_id')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('seat_count')->nullable()->after('car_color_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_cars', static function (Blueprint $table) {
            $table->dropForeignIdFor(CarColor::class);
            $table->dropColumn(['car_color_id', 'seat_count']);
        });
    }
};
