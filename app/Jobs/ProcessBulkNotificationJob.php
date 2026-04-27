<?php

namespace App\Jobs;

use App\Enum\NotificationStatusEnum;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

class ProcessBulkNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(private readonly int $notificationId)
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $notification = Notification::query()->find($this->notificationId);

        if (!$notification || in_array($notification->status, [NotificationStatusEnum::SENT, NotificationStatusEnum::SENDING])) {
            return;
        }

        $targeting = NotificationService::instance()->resolveTargeting($notification);

        $chunkJobs = [];
        $totalRecipients = 0;
        $chunkSize = 500;

        $targeting->query()
            ->select('id')
            ->chunk($chunkSize, function ($users) use ($notification, &$chunkJobs, &$totalRecipients) {
                $userIds = $users->pluck('id')->toArray();
                $totalRecipients += count($userIds);
                $chunkJobs[] = new SendNotificationChunkJob($notification->id, $userIds);
            });

        if (empty($chunkJobs)) {
            $notification->update([
                'status' => NotificationStatusEnum::SENT,
                'sent_at' => now(),
                'total_recipients' => 0,
            ]);
            return;
        }

        $chunkJobs[] = new FinalizeNotificationSendJob($notification->id);

        // Set SENDING and dispatch atomically — only after chain is ready
        $notification->update([
            'status' => NotificationStatusEnum::SENDING,
            'total_recipients' => $totalRecipients,
        ]);

        Bus::chain($chunkJobs)->dispatch();
    }

    public function failed(\Throwable $e): void
    {
        Notification::query()
            ->where('id', $this->notificationId)
            ->where('status', NotificationStatusEnum::SENDING)
            ->update(['status' => NotificationStatusEnum::CANCELLED]);
    }
}
