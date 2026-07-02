<?php

namespace App\Jobs;

use App\Enum\NotificationChannelEnum;
use App\Enum\NotificationStatusEnum;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\User;
use App\Models\UserDeviceToken;
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

        // Push: batch all tokens in one multicast call (max 500), fast — handled inline.
        if ($notification->hasChannel(NotificationChannelEnum::PUSH)) {
            $this->sendPushBatch($notification, $recipients->all());
        }

        // SMS: single bulk gateway request for the whole chunk, fast — handled inline.
        if ($notification->hasChannel(NotificationChannelEnum::SMS)) {
            $this->sendSmsBatch($notification, $users->all(), $recipients->all());
        }

        // Email: no bulk transport (per-recipient SMTP) — dispatch one job per user so a
        // slow SMTP call can never time out this batch job or re-trigger push/SMS.
        if ($notification->hasChannel(NotificationChannelEnum::EMAIL)) {
            foreach ($this->userIds as $userId) {
                if ($users->has($userId) && $recipients->has($userId)) {
                    SendUserEmailJob::dispatch($notification->id, $userId);
                }
            }
        }
    }

    /**
     * Send the SMS to all not-yet-delivered recipients in a single bulk gateway request.
     */
    private function sendSmsBatch(Notification $notification, array $users, array $recipients): void
    {
        // Idempotent on retry: only send to users not already delivered.
        $targetUsers = [];
        foreach ($users as $userId => $user) {
            $recipient = $recipients[$userId] ?? null;
            if ($recipient && $recipient->sms_sent_at === null) {
                $targetUsers[] = $user;
            }
        }

        if (empty($targetUsers)) {
            return;
        }

        $results = SmsNotificationService::instance()->sendBulkToUsers($targetUsers, $notification);

        $now = now();
        $updates = [];

        foreach ($results as $userId => $result) {
            if (!isset($recipients[$userId])) {
                continue;
            }

            $updates[$userId] = [
                'sms_sent_at' => $result['sent_at'],
                'sms_failed_at' => $result['sent_at'] ? null : $now,
                'sms_error' => $result['error'],
            ];
        }

        $this->bulkUpdateRecipients($notification->id, $updates);
    }

    /**
     * Collect all active FCM tokens for users, send one multicast per 500-token batch.
     */
    private function sendPushBatch(Notification $notification, array $recipients): void
    {
        // Idempotent on retry: only push to users not already delivered.
        $recipients = array_filter(
            $recipients,
            fn(NotificationRecipient $recipient) => $recipient->push_sent_at === null
        );

        if (empty($recipients)) {
            return;
        }

        $userIds = array_keys($recipients);

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

        $now = now();

        if (empty($allTokens)) {
            $noTokenUpdates = [];
            foreach ($recipients as $userId => $recipient) {
                $noTokenUpdates[$userId] = [
                    'push_failed_at' => $now,
                    'push_error' => 'no_device_tokens',
                ];
            }
            $this->bulkUpdateRecipients($notification->id, $noTokenUpdates);
            return;
        }

        // FCM multicast limit is 500 tokens
        $updates = [];
        foreach (array_chunk($allTokens, 500) as $tokenChunk) {
            $result = FirebaseService::instance()->sendMulticast($tokenChunk, $notification);

            $sentAt = $result['sent_at'];
            $error = $result['error'];

            foreach ($tokenChunk as $token) {
                $userId = $tokenToUserId[$token] ?? null;
                if ($userId && isset($recipients[$userId])) {
                    $updates[$userId] = [
                        'push_sent_at' => $sentAt,
                        'push_failed_at' => $sentAt ? null : $now,
                        'push_error' => $error,
                    ];
                }
            }
        }

        $this->bulkUpdateRecipients($notification->id, $updates);
    }

    /**
     * Apply per-user recipient updates in as few queries as possible by grouping
     * users that share an identical update payload into one whereIn() update.
     *
     * @param array<int,array<string,mixed>> $updatesByUser userId => column/value payload
     */
    private function bulkUpdateRecipients(int $notificationId, array $updatesByUser): void
    {
        if (empty($updatesByUser)) {
            return;
        }

        $groups = [];
        foreach ($updatesByUser as $userId => $payload) {
            $key = md5(serialize($payload));
            $groups[$key]['payload'] = $payload;
            $groups[$key]['user_ids'][] = $userId;
        }

        foreach ($groups as $group) {
            NotificationRecipient::query()
                ->where('notification_id', $notificationId)
                ->whereIn('user_id', $group['user_ids'])
                ->update($group['payload']);
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
