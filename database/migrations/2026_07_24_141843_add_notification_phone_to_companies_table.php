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
        Schema::table('companies', static function (Blueprint $table) {
            // Dedicated phone for new-order SMS alerts, verified via OTP.
            $table->string('notification_phone', 30)->nullable()->after('phone');
            $table->dateTime('notification_phone_verified_at')->nullable()->after('notification_phone');
        });
    }

    public function down(): void
    {
        Schema::table('companies', static function (Blueprint $table) {
            $table->dropColumn(['notification_phone', 'notification_phone_verified_at']);
        });
    }
};
