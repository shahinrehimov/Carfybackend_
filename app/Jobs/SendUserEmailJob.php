<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\User;
use App\Services\EmailNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendUserEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        private readonly int $notificationId,
        private readonly int $userId,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $recipient = NotificationRecipient::query()
            ->where('notification_id', $this->notificationId)
            ->where('user_id', $this->userId)
            ->first();

        // Skip if recipient row is gone or email already delivered (idempotent on retry).
        if (!$recipient || $recipient->email_sent_at !== null) {
            return;
        }

        $notification = Notification::query()->with('translatable')->find($this->notificationId);
        $user = User::query()->find($this->userId);

        if (!$notification || !$user) {
            return;
        }

        $result = EmailNotificationService::instance()->sendToUser($user, $notification);

        $recipient->update([
            'email_sent_at' => $result['sent_at'],
            'email_failed_at' => $result['sent_at'] ? null : now(),
            'email_error' => $result['error'],
        ]);
    }
}
