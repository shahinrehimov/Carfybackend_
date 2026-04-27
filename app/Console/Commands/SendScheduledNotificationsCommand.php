<?php

namespace App\Console\Commands;

use App\Enum\NotificationStatusEnum;
use App\Models\Notification;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendScheduledNotificationsCommand extends Command
{
    protected $signature = 'notifications:send-scheduled';
    protected $description = 'Dispatch approved scheduled notifications that are due';

    public function handle(): void
    {
        $notifications = Notification::query()
            ->where('status', NotificationStatusEnum::APPROVED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', Carbon::now())
            ->get();

        foreach ($notifications as $notification) {
            try {
                NotificationService::instance()->send($notification);
                $this->line("  Sent: notification #{$notification->id}");
            } catch (\Throwable $e) {
                $this->error("  Failed #{$notification->id}: {$e->getMessage()}");
            }
        }

        $this->info("Processed {$notifications->count()} scheduled notification(s).");
    }
}
