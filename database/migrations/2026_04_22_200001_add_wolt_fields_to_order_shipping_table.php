<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_shipping', function (Blueprint $table) {
            $table->json('wolt_deliveries')->nullable()->after('refunded_at');
            $table->string('wolt_status')->nullable()->after('wolt_deliveries');
        });
    }

    public function down(): void
    {
        Schema::table('order_shipping', function (Blueprint $table) {
            $table->dropColumn(['wolt_deliveries', 'wolt_status']);
        });
    }
};
