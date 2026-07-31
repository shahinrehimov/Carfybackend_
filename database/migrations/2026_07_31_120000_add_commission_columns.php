<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rate overrides. NULL means "fall through to the next level"
        // (company → category → default_commission_percent setting).
        Schema::table('companies', static function (Blueprint $table) {
            $table->decimal('commission_percent', 5, 2)->nullable()->after('show_reviews');
        });

        Schema::table('categories', static function (Blueprint $table) {
            $table->decimal('commission_percent', 5, 2)->nullable()->after('is_active');
        });

        // Snapshot taken when the order is placed, so a later rate change never
        // rewrites past reports. commission_amount is stored in cents, like every
        // other money column in this schema.
        Schema::table('order_items', static function (Blueprint $table) {
            $table->decimal('commission_percent', 5, 2)->nullable()->after('amount_final');
            $table->bigInteger('commission_amount')->nullable()->after('commission_percent');
        });
    }

    public function down(): void
    {
        Schema::table('companies', static function (Blueprint $table) {
            $table->dropColumn('commission_percent');
        });

        Schema::table('categories', static function (Blueprint $table) {
            $table->dropColumn('commission_percent');
        });

        Schema::table('order_items', static function (Blueprint $table) {
            $table->dropColumn(['commission_percent', 'commission_amount']);
        });
    }
};
