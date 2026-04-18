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
        Schema::table('orders', static function (Blueprint $table) {
            $table->text('cancel_reason')->nullable()->after('status');
            $table->string('cancelled_by_type')->nullable()->after('cancel_reason');
            $table->unsignedBigInteger('cancelled_by_id')->nullable()->after('cancelled_by_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', static function (Blueprint $table) {
            $table->dropColumn(['cancel_reason', 'cancelled_by_type', 'cancelled_by_id']);
        });
    }
};
