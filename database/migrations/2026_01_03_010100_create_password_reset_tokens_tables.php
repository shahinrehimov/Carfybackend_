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
        // Rename existing password_reset_tokens to password_reset_tokens_users
        Schema::rename('password_reset_tokens', 'password_reset_tokens_users');

        Schema::create('password_reset_tokens_admins', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('password_reset_tokens_company_admins', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens_admins');
        Schema::dropIfExists('password_reset_tokens_company_admins');
        Schema::rename('password_reset_tokens_users', 'password_reset_tokens');
    }
};
