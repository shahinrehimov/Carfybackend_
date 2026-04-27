<?php

namespace App\Services;

use App\Mail\NotificationMail;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailNotificationService
{
    private static ?EmailNotificationService $instance = null;

    private function __construct()
    {
    }

    public static function instance(): EmailNotificationService
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
        if (empty($user->email)) {
            return ['sent_at' => null, 'error' => 'no_email'];
        }

        try {
            Mail::to($user->email)->send(new NotificationMail($user, $notification));

            return ['sent_at' => now(), 'error' => null];
        } catch (Throwable $e) {
            LoggerService::instance()->log('Email notification failed', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ], true);

            return ['sent_at' => null, 'error' => $e->getMessage()];
        }
    }
}
