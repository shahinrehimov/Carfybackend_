<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->string('apple_id')->nullable();
            // Encrypted Apple refresh token, kept so we can revoke access when the
            // account is deleted (required by the App Store guidelines).
            $table->text('apple_refresh_token')->nullable();

            $table->unique(['apple_id', 'is_deleted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->dropUnique(['apple_id', 'is_deleted']);

            $table->dropColumn('apple_id');
            $table->dropColumn('apple_refresh_token');
        });
    }
};
