<?php

namespace App\Services;

use App\Enum\NotificationActionTypeEnum;
use App\Enum\NotificationEventTypeEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Notification;

class NotificationDispatchService
{
    private static ?NotificationDispatchService $instance = null;

    private function __construct()
    {
    }

    public static function instance(): NotificationDispatchService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function orderPlaced(Order $order): void
    {
        $user = $order->user;

        if (!$user) {
            return;
        }

        $translations = $this->buildTranslations(
            NotificationEventTypeEnum::ORDER_PLACED,
            ['@code' => $order->code ?? $order->uuid]
        );

        NotificationService::instance()->createAuto(
            NotificationEventTypeEnum::ORDER_PLACED,
            $translations,
            $user,
            [
                'action_type' => NotificationActionTypeEnum::ORDER->value,
                'action_id' => $order->id,
            ]
        );

        // Notify seller(s)
        $this->newOrderReceived($order);
    }

    public function orderStatusChanged(Order $order): void
    {
        $user = $order->user;

        if (!$user) {
            return;
        }

        $translations = $this->buildTranslations(
            NotificationEventTypeEnum::ORDER_STATUS_CHANGED,
            [
                '@code' => $order->code ?? $order->uuid,
                '@status' => $order->status->value,
            ]
        );

        NotificationService::instance()->createAuto(
            NotificationEventTypeEnum::ORDER_STATUS_CHANGED,
            $translations,
            $user,
            [
                'action_type' => NotificationActionTypeEnum::ORDER->value,
                'action_id' => $order->id,
            ]
        );
    }

    public function orderCancelled(Order $order): void
    {
        $user = $order->user;

        if (!$user) {
            return;
        }

        $translations = $this->buildTranslations(
            NotificationEventTypeEnum::ORDER_CANCELLED,
            ['@code' => $order->code ?? $order->uuid]
        );

        NotificationService::instance()->createAuto(
            NotificationEventTypeEnum::ORDER_CANCELLED,
            $translations,
            $user,
            [
                'action_type' => NotificationActionTypeEnum::ORDER->value,
                'action_id' => $order->id,
            ]
        );
    }

    public function orderDelivered(Order $order): void
    {
        $user = $order->user;

        if (!$user) {
            return;
        }

        $translations = $this->buildTranslations(
            NotificationEventTypeEnum::ORDER_DELIVERED,
            ['@code' => $order->code ?? $order->uuid]
        );

        NotificationService::instance()->createAuto(
            NotificationEventTypeEnum::ORDER_DELIVERED,
            $translations,
            $user,
            [
                'action_type' => NotificationActionTypeEnum::ORDER->value,
                'action_id' => $order->id,
            ]
        );
    }

    public function newOrderReceived(Order $order): void
    {
        // Load all company admins for sellers who have items in this order
        $companyAdminIds = $order->items()
            ->with('companyProductVariant.company.admins')
            ->get()
            ->flatMap(fn($item) => $item->companyProductVariant?->company?->admins ?? collect())
            ->unique('id');

        if ($companyAdminIds->isEmpty()) {
            return;
        }

        // CompanyAdmin users — we only notify Users model, so skip for now
        // This event notifies seller panel users (CompanyAdmin), not app Users
        // Handle via separate in-app mechanism if needed
    }

    public function orderItemCancelled(OrderItem $item): void
    {
        $order = $item->order ?? $item->load('order')->order;
        $user = $order?->user;

        if (!$user) {
            return;
        }

        $translations = $this->buildTranslations(
            NotificationEventTypeEnum::ORDER_ITEM_CANCELLED,
            ['@code' => $order->code ?? $order->uuid]
        );

        NotificationService::instance()->createAuto(
            NotificationEventTypeEnum::ORDER_ITEM_CANCELLED,
            $translations,
            $user,
            [
                'action_type' => NotificationActionTypeEnum::ORDER->value,
                'action_id' => $order->id,
            ]
        );
    }

    public function notificationPendingApproval(Notification $notification): void
    {
        // Notify all active admins about pending seller notification
        $admins = \App\Models\Admin::query()->get();

        if ($admins->isEmpty()) {
            return;
        }

        // Admin notifications use in_app only (system alert)
        // This will be handled via a separate admin notification mechanism
        // as Admin is not a User model in this project
    }

    /**
     * Build translations array for all languages for a given event type.
     *
     * @param NotificationEventTypeEnum $eventType
     * @param array $replacements  placeholder => value pairs
     * @return array  [['language_id' => int, 'title' => string, 'body' => string], ...]
     */
    private function buildTranslations(NotificationEventTypeEnum $eventType, array $replacements = []): array
    {
        $langService = LangService::instance();
        $languages = $langService->getLanguages();
        $originalLang = $langService->getCurrentLang();
        $translations = [];

        foreach ($languages as $language) {
            // Set language context so getLang fetches (or creates) translation for this language
            $langService->setLanguage($language['key']);

            [$title, $body] = match ($eventType) {
                NotificationEventTypeEnum::ORDER_PLACED => [
                    $langService->setDefault('Your order has been placed!')->getLang('notification_title_order_placed', $replacements),
                    $langService->setDefault('Your order #@code has been placed successfully. We will notify you when it is confirmed.')->getLang('notification_body_order_placed', $replacements),
                ],
                NotificationEventTypeEnum::ORDER_STATUS_CHANGED => [
                    $langService->setDefault('Order status updated')->getLang('notification_title_order_status_changed', $replacements),
                    $langService->setDefault('Your order #@code status has been updated to: @status.')->getLang('notification_body_order_status_changed', $replacements),
                ],
                NotificationEventTypeEnum::ORDER_CANCELLED => [
                    $langService->setDefault('Order cancelled')->getLang('notification_title_order_cancelled', $replacements),
                    $langService->setDefault('Your order #@code has been cancelled.')->getLang('notification_body_order_cancelled', $replacements),
                ],
                NotificationEventTypeEnum::ORDER_DELIVERED => [
                    $langService->setDefault('Order delivered!')->getLang('notification_title_order_delivered', $replacements),
                    $langService->setDefault('Your order #@code has been delivered. Thank you for shopping with us!')->getLang('notification_body_order_delivered', $replacements),
                ],
                NotificationEventTypeEnum::ORDER_ITEM_CANCELLED => [
                    $langService->setDefault('Item cancelled')->getLang('notification_title_order_item_cancelled', $replacements),
                    $langService->setDefault('An item in your order #@code has been cancelled and a refund has been initiated.')->getLang('notification_body_order_item_cancelled', $replacements),
                ],
                default => ['Notification', 'You have a new notification.'],
            };

            $translations[] = [
                'language_id' => $language['id'],
                'title' => $title,
                'body' => $body,
            ];
        }

        // Restore original language context
        $langService->setLanguage($originalLang);

        return $translations;
    }
}
