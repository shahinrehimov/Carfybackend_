<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referred_user_perks', static function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('free_delivery_used_count')->default(0)->comment('Max 3');
            $table->unsignedTinyInteger('discount_orders_count')->default(0)->comment('Max 3; 1% discount applied per order');
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referred_user_perks');
    }
};
