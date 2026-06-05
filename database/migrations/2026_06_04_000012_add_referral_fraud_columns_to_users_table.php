<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->string('registration_ip', 45)->nullable()->comment('IP captured at registration (referral fraud detection)');
            $table->string('device_fingerprint', 500)->nullable()->comment('Device fingerprint at registration (referral fraud detection)');

            $table->index('registration_ip');
            $table->index('device_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->dropIndex(['registration_ip']);
            $table->dropIndex(['device_fingerprint']);
            $table->dropColumn(['registration_ip', 'device_fingerprint']);
        });
    }
};
