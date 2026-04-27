<?php

namespace App\Jobs;

use App\Enum\NotificationChannelEnum;
use App\Enum\NotificationStatusEnum;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\User;
use App\Models\UserDeviceToken;
use App\Services\EmailNotificationService;
use App\Services\FirebaseService;
use App\Services\SmsNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNotificationChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        private readonly int $notificationId,
        private readonly array $userIds,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $notification = Notification::query()
            ->with('translatable')
            ->find($this->notificationId);

        if (!$notification) {
            return;
        }

        $now = now();

        // Bulk insert recipients (skip existing)
        $rows = array_map(fn($uid) => [
            'notification_id' => $notification->id,
            'user_id' => $uid,
            'created_at' => $now,
            'updated_at' => $now,
        ], $this->userIds);

        NotificationRecipient::query()->upsert(
            $rows,
            ['notification_id', 'user_id'],
            ['updated_at']
        );

        $users = User::query()
            ->whereIn('id', $this->userIds)
            ->get()
            ->keyBy('id');

        $recipients = NotificationRecipient::query()
            ->where('notification_id', $notification->id)
            ->whereIn('user_id', $this->userIds)
            ->get()
            ->keyBy('user_id');

        // Push: batch all tokens in one multicast call (max 500)
        if ($notification->hasChannel(NotificationChannelEnum::PUSH)) {
            $this->sendPushBatch($notification, $users->all(), $recipients->all());
        }

        // SMS + Email: per-user (these are slow channels, handle individually)
        foreach ($this->userIds as $userId) {
            $user = $users->get($userId);
            $recipient = $recipients->get($userId);

            if (!$user || !$recipient) {
                continue;
            }

            if ($notification->hasChannel(NotificationChannelEnum::SMS)) {
                $result = SmsNotificationService::instance()->sendToUser($user, $notification);
                $recipient->update([
                    'sms_sent_at' => $result['sent_at'],
                    'sms_failed_at' => $result['sent_at'] ? null : now(),
                    'sms_error' => $result['error'],
                ]);
            }

            if ($notification->hasChannel(NotificationChannelEnum::EMAIL)) {
                $result = EmailNotificationService::instance()->sendToUser($user, $notification);
                $recipient->update([
                    'email_sent_at' => $result['sent_at'],
                    'email_failed_at' => $result['sent_at'] ? null : now(),
                    'email_error' => $result['error'],
                ]);
            }
        }

    }

    /**
     * Collect all active FCM tokens for users, send one multicast per 500-token batch.
     */
    private function sendPushBatch(Notification $notification, array $users, array $recipients): void
    {
        $userIds = array_keys($users);

        $tokensByUser = UserDeviceToken::query()
            ->whereIn('user_id', $userIds)
            ->where('is_active', true)
            ->get()
            ->groupBy('user_id');

        $allTokens = [];
        $tokenToUserId = [];

        foreach ($tokensByUser as $userId => $tokens) {
            foreach ($tokens as $deviceToken) {
                $allTokens[] = $deviceToken->token;
                $tokenToUserId[$deviceToken->token] = $userId;
            }
        }

        if (empty($allTokens)) {
            foreach ($recipients as $recipient) {
                $recipient->update([
                    'push_failed_at' => now(),
                    'push_error' => 'no_device_tokens',
                ]);
            }
            return;
        }

        // FCM multicast limit is 500 tokens
        foreach (array_chunk($allTokens, 500) as $tokenChunk) {
            $result = FirebaseService::instance()->sendMulticast($tokenChunk, $notification);

            $sentAt = $result['sent_at'];
            $error = $result['error'];

            foreach ($tokenChunk as $token) {
                $userId = $tokenToUserId[$token] ?? null;
                if ($userId && isset($recipients[$userId])) {
                    $recipients[$userId]->update([
                        'push_sent_at' => $sentAt,
                        'push_failed_at' => $sentAt ? null : now(),
                        'push_error' => $error,
                    ]);
                }
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Notification::query()
            ->where('id', $this->notificationId)
            ->where('status', NotificationStatusEnum::SENDING)
            ->update(['status' => NotificationStatusEnum::CANCELLED]);
    }
}
