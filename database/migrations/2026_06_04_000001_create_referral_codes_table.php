<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_codes', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 50)->unique()->comment('Stored uppercase, case-insensitive matching');
            $table->boolean('is_custom')->default(false)->comment('True if user manually set this code');
            $table->boolean('is_active')->default(true)->comment('False when user changes to a new code');
            $table->timestamps();

            $table->index(['code', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_codes');
    }
};
