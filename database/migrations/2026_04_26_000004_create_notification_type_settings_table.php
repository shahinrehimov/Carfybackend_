<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_type_settings', static function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 60)->unique()->comment('NotificationEventTypeEnum value');
            $table->boolean('is_active')->default(true);
            $table->json('channels')->comment('NotificationChannelEnum values');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_type_settings');
    }
};
