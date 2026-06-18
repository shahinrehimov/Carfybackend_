<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_usages', static function (Blueprint $table) {
            $table->unsignedInteger('refunded_amount')->default(0)
                ->after('amount_used')
                ->comment('Cents of the spent balance already returned (supports partial item refunds)');
        });

        // Backfill: usages already marked fully refunded should reflect that amount.
        DB::table('referral_usages')
            ->whereNotNull('refunded_at')
            ->update(['refunded_amount' => DB::raw('amount_used')]);
    }

    public function down(): void
    {
        Schema::table('referral_usages', static function (Blueprint $table) {
            $table->dropColumn('refunded_amount');
        });
    }
};
