<?php

namespace App\Enum;

enum NotificationEventTypeEnum: string
{
    // User-facing order events
    case ORDER_PLACED = 'order_placed';
    case ORDER_STATUS_CHANGED = 'order_status_changed';
    case ORDER_CANCELLED = 'order_cancelled';
    case ORDER_DELIVERED = 'order_delivered';
    case ORDER_ITEM_CANCELLED = 'order_item_cancelled';

    // Seller-facing events
    case NEW_ORDER_RECEIVED = 'new_order_received';

    // Admin-facing events
    case NOTIFICATION_PENDING_APPROVAL = 'notification_pending_approval';

    public static function getDefaultChannels(self $type): array
    {
        return match ($type) {
            self::ORDER_PLACED,
            self::ORDER_STATUS_CHANGED,
            self::ORDER_CANCELLED,
            self::ORDER_DELIVERED,
            self::ORDER_ITEM_CANCELLED => [
                NotificationChannelEnum::IN_APP->value,
                NotificationChannelEnum::PUSH->value,
            ],
            self::NEW_ORDER_RECEIVED => [
                NotificationChannelEnum::IN_APP->value,
                NotificationChannelEnum::PUSH->value,
            ],
            self::NOTIFICATION_PENDING_APPROVAL => [
                NotificationChannelEnum::IN_APP->value,
            ],
        };
    }
}
