<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_balances', static function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('pending_balance')->default(0)->comment('Cents');
            $table->unsignedInteger('available_balance')->default(0)->comment('Cents');
            $table->unsignedInteger('lifetime_earned')->default(0)->comment('Cents');
            $table->unsignedInteger('lifetime_withdrawn')->default(0)->comment('Cents');
            $table->unsignedInteger('lifetime_spent_on_platform')->default(0)->comment('Cents');
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_balances');
    }
};
