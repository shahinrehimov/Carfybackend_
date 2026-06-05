<?php

use App\Enum\Referral\ReferralAttributionStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_attributions', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default(ReferralAttributionStatusEnum::PENDING->value)
                ->comment('ReferralAttributionStatusEnum: pending|active|fraud_blocked');
            $table->foreignId('first_qualifying_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_attributions');
    }
};
