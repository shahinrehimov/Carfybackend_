<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_recipients', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable()->comment('in_app read status');
            $table->timestamp('in_app_deleted_at')->nullable()->comment('user removed from their list');
            $table->timestamp('push_sent_at')->nullable();
            $table->timestamp('push_failed_at')->nullable();
            $table->text('push_error')->nullable();
            $table->timestamp('sms_sent_at')->nullable();
            $table->timestamp('sms_failed_at')->nullable();
            $table->text('sms_error')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('email_failed_at')->nullable();
            $table->text('email_error')->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'user_id'], 'notification_recipients_unique');
            $table->index(['user_id', 'in_app_deleted_at', 'created_at']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_recipients');
    }
};
