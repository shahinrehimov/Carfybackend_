<?php

namespace App\Jobs;

use App\Enum\NotificationStatusEnum;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FinalizeNotificationSendJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly int $notificationId)
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        Notification::query()
            ->where('id', $this->notificationId)
            ->where('status', NotificationStatusEnum::SENDING)
            ->update([
                'status' => NotificationStatusEnum::SENT,
                'sent_at' => Carbon::now(),
            ]);
    }

    public function failed(\Throwable $e): void
    {
        Notification::query()
            ->where('id', $this->notificationId)
            ->where('status', NotificationStatusEnum::SENDING)
            ->update(['status' => NotificationStatusEnum::CANCELLED]);
    }
}
