<?php

use App\Enum\Referral\ReferralEarningStatusEnum;
use App\Enum\Referral\ReferralTierEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_earnings', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('tier_at_time', 20)->comment('ReferralTierEnum snapshot at earning creation');
            $table->unsignedInteger('rate')->comment('Rate in basis points (100 = 1%)');
            $table->unsignedInteger('order_amount')->comment('Order subtotal in cents at earning time');
            $table->unsignedInteger('amount')->comment('Earned amount in cents');
            $table->string('status', 20)->default(ReferralEarningStatusEnum::PENDING->value)
                ->comment('ReferralEarningStatusEnum: pending|available|refunded|cancelled|fraud_hold');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('available_at')->nullable()->comment('When pending period ends (delivered_at + N days)');
            $table->timestamp('credited_at')->nullable()->comment('When moved to available_balance');
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_id', 'status']);
            $table->index(['status', 'available_at']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_earnings');
    }
};
