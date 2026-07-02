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
    private ?Messaging $messaging = null;

    private function __construct()
    {
        try {
            $this->messaging = app(Messaging::class);
        } catch (Throwable) {
            // kreait not installed — push notifications silently skipped
        }
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
        if ($this->messaging === null) {
            return ['sent_at' => null, 'error' => 'firebase_unavailable'];
        }

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
        if ($this->messaging === null) {
            return ['sent_at' => null, 'error' => 'firebase_unavailable'];
        }

        if (empty($tokens)) {
            return ['sent_at' => null, 'error' => 'empty_tokens'];
        }

        $title = $notification->getLang('title');
        $body = strip_tags($notification->getLang('body'));

        $data = array_filter([
            'notification_uuid' => (string) $notification->uuid,
            'action_type' => (string) $notification->action_type->value,
            'action_id' => (string) ($notification->action_id ?? ''),
        ]);

        try {
            $message = CloudMessage::new()
                ->withNotification(FcmNotification::create($title, $body))
                ->withData($data);

            $report = $this->messaging->sendMulticast($message, $tokens);

            $sent = $report->successes()->count() > 0;

            // Token cleanup is a best-effort side effect — never let a failure here
            // turn an already-delivered push into a recorded error.
            try {
                $this->deactivateFailedTokens($report);
            } catch (Throwable $e) {
                LoggerService::instance()->log('Firebase token cleanup failed', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ], true);
            }

            if (!$sent) {
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
            // kreait 8.x SendReport API: a token is dead when it is unknown/unregistered
            // or the target itself was invalid.
            if ($failure->messageWasSentToUnknownToken() || $failure->messageTargetWasInvalid()) {
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
