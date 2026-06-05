<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_usages', static function (Blueprint $table) {
            $table->timestamp('refunded_at')->nullable()
                ->comment('Set when the spent balance is returned (order cancelled/refunded)');
        });
    }

    public function down(): void
    {
        Schema::table('referral_usages', static function (Blueprint $table) {
            $table->dropColumn('refunded_at');
        });
    }
};
