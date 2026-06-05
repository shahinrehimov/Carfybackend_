<?php

use App\Enum\Referral\ReferralFraudFlagTypeEnum;
use App\Enum\Referral\ReferralFraudSeverityEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_fraud_flags', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('matched_with_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('flag_type', 20)->comment('ReferralFraudFlagTypeEnum: card|phone|device|ip|velocity');
            $table->string('severity', 20)->comment('ReferralFraudSeverityEnum: hard_block|soft_flag');
            $table->boolean('resolved')->default(false);
            $table->foreignId('resolved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('resolution', 20)->nullable()->comment('approved|rejected');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'resolved']);
            $table->index(['severity', 'resolved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_fraud_flags');
    }
};
