<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Throwable;

class SmsNotificationService
{
    private static ?SmsNotificationService $instance = null;

    private function __construct()
    {
    }

    public static function instance(): SmsNotificationService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Returns ['sent_at' => now, 'error' => null] or ['sent_at' => null, 'error' => message].
     */
    public function sendToUser(User $user, Notification $notification): array
    {
        if (empty($user->phone)) {
            return ['sent_at' => null, 'error' => 'no_phone'];
        }

        $body = strip_tags($notification->getLang('body'));
        $title = $notification->getLang('title');

        $message = $title . "\n" . $body;

        try {
            SmsService::instance()->sendInfo($user->phone, $message);

            return ['sent_at' => now(), 'error' => null];
        } catch (Throwable $e) {
            return ['sent_at' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send the notification SMS to many users in a single gateway request.
     *
     * @param User[] $users
     * @return array<int,array{sent_at:mixed,error:?string}> keyed by user id
     */
    public function sendBulkToUsers(array $users, Notification $notification): array
    {
        $body = strip_tags($notification->getLang('body'));
        $title = $notification->getLang('title');
        $message = $title . "\n" . $body;

        $results = [];
        $phoneToUserIds = [];

        foreach ($users as $user) {
            if (empty($user->phone)) {
                $results[$user->id] = ['sent_at' => null, 'error' => 'no_phone'];
                continue;
            }

            $phoneToUserIds[$user->phone][] = $user->id;
        }

        if (empty($phoneToUserIds)) {
            return $results;
        }

        $errorMap = SmsService::instance()->sendInfoBulk(array_keys($phoneToUserIds), $message);
        $now = now();

        foreach ($phoneToUserIds as $phone => $userIds) {
            // null = accepted; a string carries the gateway's rejection reason.
            $error = array_key_exists($phone, $errorMap) ? $errorMap[$phone] : 'sms_not_processed';

            foreach ($userIds as $userId) {
                $results[$userId] = $error === null
                    ? ['sent_at' => $now, 'error' => null]
                    : ['sent_at' => null, 'error' => $error];
            }
        }

        return $results;
    }
}
