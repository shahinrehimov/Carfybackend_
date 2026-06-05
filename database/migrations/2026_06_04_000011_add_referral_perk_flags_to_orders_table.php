<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table) {
            $table->boolean('referral_free_delivery_applied')->default(false)
                ->comment('Referred-user free delivery perk consumed by this order');
            $table->boolean('referral_discount_applied')->default(false)
                ->comment('Referred-user 1% discount perk consumed by this order');
        });
    }

    public function down(): void
    {
        Schema::table('orders', static function (Blueprint $table) {
            $table->dropColumn(['referral_free_delivery_applied', 'referral_discount_applied']);
        });
    }
};
