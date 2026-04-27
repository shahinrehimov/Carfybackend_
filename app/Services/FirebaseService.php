<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserDeviceToken;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Throwable;

class FirebaseService
{
    private static ?FirebaseService $instance = null;
    private Messaging $messaging;

    private function __construct()
    {
        $this->messaging = app(Messaging::class);
    }

    public static function instance(): FirebaseService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Send push notification to a single user (all their active devices).
     * Returns ['sent_at' => now, 'error' => null] or ['sent_at' => null, 'error' => message].
     */
    public function sendToUser(User $user, Notification $notification): array
    {
        $tokens = UserDeviceToken::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return ['sent_at' => null, 'error' => 'no_device_tokens'];
        }

        return $this->sendMulticast($tokens, $notification);
    }

    /**
     * Send to a list of FCM tokens (max 500 per call — enforced by caller).
     */
    public function sendMulticast(array $tokens, Notification $notification): array
    {
        if (empty($tokens)) {
            return ['sent_at' => null, 'error' => 'empty_tokens'];
        }

        $title = $notification->getLang('title');
        $body = strip_tags($notification->getLang('body'));

        $data = array_filter([
            'notification_uuid' => $notification->uuid,
            'action_type' => $notification->action_type->value,
            'action_id' => $notification->action_id ?? '',
        ]);

        try {
            $message = CloudMessage::new()
                ->withNotification(FcmNotification::create($title, $body))
                ->withData($data);

            $report = $this->messaging->sendMulticast($message, $tokens);

            $this->deactivateFailedTokens($report);

            if ($report->successes()->count() === 0) {
                return ['sent_at' => null, 'error' => 'all_tokens_failed'];
            }

            return ['sent_at' => now(), 'error' => null];
        } catch (Throwable $e) {
            LoggerService::instance()->log('Firebase push failed', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ], true);

            return ['sent_at' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Deactivate FCM tokens that are no longer valid.
     */
    private function deactivateFailedTokens(MulticastSendReport $report): void
    {
        $invalidTokens = [];

        foreach ($report->failures()->getItems() as $failure) {
            if ($failure->messageWasSentWithoutError()) {
                continue;
            }

            $error = $failure->error();
            if ($error && str_contains($error->getMessage(), 'UNREGISTERED')) {
                $invalidTokens[] = $failure->target()->value();
            }
        }

        if (!empty($invalidTokens)) {
            UserDeviceToken::query()
                ->whereIn('token', $invalidTokens)
                ->update(['is_active' => false]);
        }
    }
}
