<?php

namespace App\Services;

use App\Enum\NotificationChannelEnum;
use App\Enum\NotificationEventTypeEnum;
use App\Enum\NotificationSourceEnum;
use App\Enum\NotificationStatusEnum;
use App\Enum\NotificationTargetTypeEnum;
use App\Jobs\ProcessBulkNotificationJob;
use App\Models\Admin;
use App\Models\CompanyAdmin;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\NotificationTypeSetting;
use App\Models\User;
use App\Models\UserDeviceToken;
use App\Targeting\AllUsersTarget;
use App\Targeting\CartAbandonersTarget;
use App\Targeting\InactiveUsersTarget;
use App\Targeting\NotificationTargetingInterface;
use App\Targeting\RecentBuyersTarget;
use App\Targeting\SellerCustomersTarget;
use App\Targeting\SpecificUsersTarget;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class NotificationService
{
    private static ?NotificationService $instance = null;

    private function __construct()
    {
    }

    public static function instance(): NotificationService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Create and immediately dispatch an auto (system) notification.
     *
     * @param NotificationEventTypeEnum $eventType
     * @param array $translations [['language_id' => 1, 'title' => '...', 'body' => '...'], ...]
     * @param User|User[] $recipients
     * @param array $extra action_type, action_id, image, etc.
     * @return Notification|null
     */
    public function createAuto(
        NotificationEventTypeEnum $eventType,
        array $translations,
        User|array $recipients,
        array $extra = []
    ): ?Notification {
        $setting = NotificationTypeSetting::query()
            ->where('event_type', $eventType->value)
            ->first();

        if (!$setting || !$setting->is_active) {
            return null;
        }

        $channels = $setting->channels;

        if (empty($channels)) {
            return null;
        }

        $users = is_array($recipients) ? $recipients : [$recipients];

        $notification = Notification::query()->create([
            'uuid' => Str::uuid(),
            'source' => NotificationSourceEnum::SYSTEM,
            'event_type' => $eventType->value,
            'status' => NotificationStatusEnum::DRAFT,
            'target_type' => NotificationTargetTypeEnum::INDIVIDUAL,
            'channels' => $channels,
            'action_type' => $extra['action_type'] ?? 'none',
            'action_id' => $extra['action_id'] ?? null,
            'image' => $extra['image'] ?? null,
        ]);

        $notification->setLangs($translations);
        $notification->saveLang();

        $this->dispatchIndividual($notification, $users);

        return $notification;
    }

    /**
     * Create a manual notification (admin or seller).
     * Admin notifications go directly to approved status.
     * Seller notifications go to pending_approval.
     */
    public function createManual(array $data, Admin|CompanyAdmin $sender): Notification
    {
        $isAdmin = $sender instanceof Admin;

        if (!$isAdmin && $data['target_type'] === NotificationTargetTypeEnum::BULK->value) {
            $data['target_filters'] = ['company_id' => $sender->company_id];
        }

        $notification = Notification::query()->create([
            'uuid' => Str::uuid(),
            'source' => $isAdmin ? NotificationSourceEnum::ADMIN : NotificationSourceEnum::SELLER,
            'status' => $isAdmin ? NotificationStatusEnum::APPROVED : NotificationStatusEnum::PENDING_APPROVAL,
            'target_type' => $data['target_type'],
            'target_filters' => $data['target_filters'] ?? null,
            'channels' => $data['channels'],
            'action_type' => $data['action_type'] ?? 'none',
            'action_id' => $data['action_id'] ?? null,
            'image' => $data['image'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'sender_type' => $sender->getMorphClass(),
            'sender_id' => $sender->id,
        ]);

        $notification->setLangs($data['translations']);
        $notification->saveLang();

        if ($isAdmin && empty($data['scheduled_at'])) {
            $this->send($notification);
        }

        return $notification;
    }

    /**
     * Send an approved or admin-draft notification immediately.
     */
    public function send(Notification $notification): void
    {
        if (!$notification->status->isSendable()) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('This notification cannot be sent in its current status.')
                    ->getLang('notification_cannot_send')
            );
        }

        if ($notification->target_type === NotificationTargetTypeEnum::INDIVIDUAL) {
            $userIds = $notification->target_filters['user_ids'] ?? [];
            $users = User::query()->whereIn('id', $userIds)->get()->all();
            $this->dispatchIndividual($notification, $users);
        } else {
            ProcessBulkNotificationJob::dispatch($notification->id);
        }
    }

    /**
     * Resend a notification (creates a fresh send, resets delivery records).
     * Seller resend goes back to pending_approval.
     */
    public function resend(Notification $notification, Admin|CompanyAdmin $requester): Notification
    {
        if ($notification->status !== NotificationStatusEnum::SENT && $notification->status !== NotificationStatusEnum::CANCELLED) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Only sent or cancelled notifications can be resent.')
                    ->getLang('notification_resend_invalid_status')
            );
        }

        $isAdmin = $requester instanceof Admin;

        $newStatus = $isAdmin ? NotificationStatusEnum::APPROVED : NotificationStatusEnum::PENDING_APPROVAL;

        $notification->loadMissing('translatable');

        $clone = $notification->replicate(['sent_at', 'total_recipients', 'approved_by_id', 'approved_at', 'rejection_reason']);
        $clone->uuid = Str::uuid();
        $clone->status = $newStatus;
        $clone->sent_at = null;
        $clone->total_recipients = 0;
        $clone->approved_by_id = null;
        $clone->approved_at = null;
        $clone->rejection_reason = null;
        $clone->scheduled_at = null;
        $clone->sender_type = $requester->getMorphClass();
        $clone->sender_id = $requester->id;
        $clone->save();

        // Copy translations
        foreach ($notification->translatable as $translation) {
            $clone->setLang($translation->column, $translation->text, $translation->language_id);
        }
        $clone->saveLang();

        if ($isAdmin) {
            $this->send($clone);
        }

        return $clone;
    }

    /**
     * Approve a seller notification and send it.
     */
    public function approve(Notification $notification, Admin $admin): void
    {
        if ($notification->status !== NotificationStatusEnum::PENDING_APPROVAL) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Only pending notifications can be approved.')
                    ->getLang('notification_approve_invalid_status')
            );
        }

        $notification->update([
            'status' => NotificationStatusEnum::APPROVED,
            'approved_by_id' => $admin->id,
            'approved_at' => Carbon::now(),
        ]);

        if (empty($notification->scheduled_at)) {
            $this->send($notification);
        }
    }

    /**
     * Reject a seller notification.
     */
    public function reject(Notification $notification, Admin $admin, string $reason): void
    {
        if ($notification->status !== NotificationStatusEnum::PENDING_APPROVAL) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Only pending notifications can be rejected.')
                    ->getLang('notification_reject_invalid_status')
            );
        }

        $notification->update([
            'status' => NotificationStatusEnum::REJECTED,
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Dispatch individual notification to a list of users synchronously (for small lists / auto).
     */
    public function dispatchIndividual(Notification $notification, array $users): void
    {
        if (empty($users)) {
            return;
        }

        $notification->update(['status' => NotificationStatusEnum::SENDING]);

        $now = Carbon::now();
        $recipientRows = [];

        foreach ($users as $user) {
            $recipientRows[] = [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        NotificationRecipient::query()->upsert(
            $recipientRows,
            ['notification_id', 'user_id'],
            ['updated_at']
        );

        foreach ($users as $user) {
            $recipient = NotificationRecipient::query()
                ->where('notification_id', $notification->id)
                ->where('user_id', $user->id)
                ->first();

            if (!$recipient) {
                continue;
            }

            $this->deliverToRecipient($notification, $user, $recipient);
        }

        $notification->update([
            'status' => NotificationStatusEnum::SENT,
            'sent_at' => Carbon::now(),
            'total_recipients' => count($users),
        ]);
    }

    /**
     * Deliver a notification through all its channels to one user.
     */
    public function deliverToRecipient(Notification $notification, User $user, NotificationRecipient $recipient): void
    {
        if ($notification->hasChannel(NotificationChannelEnum::PUSH)) {
            $result = FirebaseService::instance()->sendToUser($user, $notification);
            $recipient->update([
                'push_sent_at' => $result['sent_at'],
                'push_failed_at' => $result['sent_at'] ? null : now(),
                'push_error' => $result['error'],
            ]);
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

    /**
     * Resolve targeting strategy from notification filters.
     */
    public function resolveTargeting(Notification $notification): NotificationTargetingInterface
    {
        $filters = $notification->target_filters ?? [];

        return match (true) {
            array_key_exists('user_ids', $filters) => new SpecificUsersTarget($filters['user_ids']),
            array_key_exists('company_id', $filters) => new SellerCustomersTarget($filters['company_id']),
            array_key_exists('recent_buyers_days', $filters) => new RecentBuyersTarget($filters['recent_buyers_days']),
            array_key_exists('cart_abandoners_days', $filters) => new CartAbandonersTarget($filters['cart_abandoners_days']),
            array_key_exists('inactive_days', $filters) => new InactiveUsersTarget($filters['inactive_days']),
            default => new AllUsersTarget(),
        };
    }

    /**
     * Preview the count of users that would receive a bulk notification.
     */
    public function previewTargetCount(string $targetType, array $filters): int
    {
        if ($targetType !== NotificationTargetTypeEnum::BULK->value) {
            return count($filters['user_ids'] ?? []);
        }

        $dummyNotification = new Notification([
            'target_type' => NotificationTargetTypeEnum::BULK,
            'target_filters' => $filters,
        ]);

        return $this->resolveTargeting($dummyNotification)->query()->count();
    }

    /**
     * Get user's in-app notifications (paginated).
     */
    public function getUserNotifications(User $user, int $perPage = 30)
    {
        return Notification::query()
            ->with([
                'translatable',
                'recipients' => fn(HasMany $q) => $q->where('user_id', $user->id),
            ])
            ->whereHas('recipients', function (Builder $query) use ($user) {
                $query->where('user_id', $user->id)
                    ->whereNull('in_app_deleted_at');
            })
            ->whereJsonContains('channels', NotificationChannelEnum::IN_APP->value)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Get unread count for badge.
     */
    public function getUnreadCount(User $user): int
    {
        return NotificationRecipient::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->whereNull('in_app_deleted_at')
            ->whereHas('notification', function (Builder $query) {
                $query->whereJsonContains('channels', NotificationChannelEnum::IN_APP->value)
                    ->where('status', NotificationStatusEnum::SENT);
            })
            ->count();
    }

    /**
     * Mark a notification as read for the current user.
     */
    public function markAsRead(Notification $notification, User $user): void
    {
        NotificationRecipient::query()
            ->where('notification_id', $notification->id)
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);
    }

    /**
     * Mark all in-app notifications as read for user.
     */
    public function markAllAsRead(User $user): void
    {
        NotificationRecipient::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->whereNull('in_app_deleted_at')
            ->update(['read_at' => Carbon::now()]);
    }

    /**
     * Soft-delete a notification from user's in-app list.
     */
    public function deleteForUser(Notification $notification, User $user): void
    {
        NotificationRecipient::query()
            ->where('notification_id', $notification->id)
            ->where('user_id', $user->id)
            ->update(['in_app_deleted_at' => Carbon::now()]);
    }

    /**
     * Soft-delete all notifications from user's in-app list.
     */
    public function deleteAllForUser(User $user): void
    {
        NotificationRecipient::query()
            ->where('user_id', $user->id)
            ->whereNull('in_app_deleted_at')
            ->update(['in_app_deleted_at' => Carbon::now()]);
    }

    /**
     * Update an editable notification's fields and/or translations.
     * Only keys present in $data are written — absent keys are left unchanged.
     */
    public function updateNotification(Notification $notification, array $data): void
    {
        $fillable = ['target_type', 'target_filters', 'channels', 'action_type', 'action_id', 'image', 'scheduled_at'];
        $updateData = array_intersect_key($data, array_flip($fillable));

        if (!empty($updateData)) {
            $notification->update($updateData);
        }

        if (!empty($data['translations'])) {
            $notification->setLangs($data['translations']);
            $notification->saveLang();
        }
    }

    /**
     * Register or refresh a user's FCM device token.
     */
    public function registerDeviceToken(User $user, string $token, string $deviceType): void
    {
        UserDeviceToken::query()->updateOrCreate(
            ['token' => $token],
            [
                'user_id' => $user->id,
                'device_type' => $deviceType,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );
    }

    /**
     * Deactivate a user's FCM device token (logout / token refresh).
     */
    public function deregisterDeviceToken(User $user, string $token): void
    {
        UserDeviceToken::query()
            ->where('token', $token)
            ->where('user_id', $user->id)
            ->update(['is_active' => false]);
    }

    /**
     * Bulk-update notification type settings (is_active + channels per row).
     */
    public function updateSettings(array $settings): void
    {
        foreach ($settings as $item) {
            NotificationTypeSetting::query()
                ->where('id', $item['id'])
                ->update([
                    'is_active' => $item['is_active'],
                    'channels' => $item['channels'],
                ]);
        }
    }

    /**
     * Create missing notification_type_settings rows for any new enum cases.
     * Returns the number of rows added.
     */
    public function syncEventTypes(): int
    {
        $existing = NotificationTypeSetting::query()->pluck('event_type')->toArray();
        $added = 0;

        foreach (NotificationEventTypeEnum::cases() as $case) {
            if (!in_array($case->value, $existing)) {
                NotificationTypeSetting::query()->create([
                    'event_type' => $case->value,
                    'is_active' => true,
                    'channels' => NotificationEventTypeEnum::getDefaultChannels($case),
                ]);
                $added++;
            }
        }

        return $added;
    }
}
