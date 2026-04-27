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
}
