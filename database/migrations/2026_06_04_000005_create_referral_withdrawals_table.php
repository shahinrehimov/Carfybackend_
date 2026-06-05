<?php

use App\Enum\Referral\ReferralWithdrawalStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_withdrawals', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('amount')->comment('Requested amount in cents');
            $table->unsignedInteger('fee')->comment('Fee in cents');
            $table->unsignedInteger('net_amount')->comment('Amount after fee in cents');
            $table->string('card_last4', 4)->nullable();
            $table->string('card_holder_name')->nullable();
            $table->string('status', 20)->default(ReferralWithdrawalStatusEnum::PENDING->value)
                ->comment('ReferralWithdrawalStatusEnum');
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('admin_note')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_withdrawals');
    }
};
