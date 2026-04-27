<?php

use App\Enum\NotificationActionTypeEnum;
use App\Enum\NotificationSourceEnum;
use App\Enum\NotificationStatusEnum;
use App\Enum\NotificationTargetTypeEnum;
use App\Models\Admin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', static function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('source', 20)->default(NotificationSourceEnum::SYSTEM->value)->comment('NotificationSourceEnum');
            $table->string('event_type', 60)->nullable()->comment('NotificationEventTypeEnum — only for auto notifications');
            $table->string('status', 30)->default(NotificationStatusEnum::DRAFT->value)->comment('NotificationStatusEnum');
            $table->string('target_type', 20)->default(NotificationTargetTypeEnum::INDIVIDUAL->value)->comment('NotificationTargetTypeEnum');
            $table->json('target_filters')->nullable()->comment('Bulk targeting criteria');
            $table->json('channels')->comment('NotificationChannelEnum values');
            $table->string('action_type', 20)->default(NotificationActionTypeEnum::NONE->value)->comment('NotificationActionTypeEnum');
            $table->string('action_id', 255)->nullable()->comment('uuid/slug/url depending on action_type');
            $table->string('image', 500)->nullable();
            $table->nullableMorphs('sender');
            $table->foreignId('approved_by_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->timestamps();
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
            $table->nullableMorphs('deletable');
            $table->string('is_deleted', 30)->default('false');
            $table->softDeletes();

            $table->index(['status', 'scheduled_at']);
            $table->index(['source', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
