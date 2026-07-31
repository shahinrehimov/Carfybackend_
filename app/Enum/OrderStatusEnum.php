<?php

namespace App\Enum;

use App\Services\LangService;
use Illuminate\Support\Str;

enum OrderStatusEnum: string
{
    case PENDING = 'pending';
    case PAYMENT_PENDING = 'payment_pending';
    case FAILED = 'failed';
    case PROCESSING = 'processing';
    case CONFIRMED = 'confirmed';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELED = 'canceled';
    case RETURNED = 'returned';
    case COURIER_ASSIGNED = 'courier_assigned';

    public static function getList(): array
    {
        $array = [];

        foreach (self::cases() as $case) {
            $array[] = [
                'id' => $case,
                'title' => LangService::instance()
                    ->setDefault(Str::title($case->value))
                    ->getLang('order_status_' . $case->value),
            ];
        }

        return $array;
    }

    /**
     * Statuses of orders that were actually placed by a customer.
     *
     * Orders sitting in pending / payment_pending never completed checkout and
     * failed ones never got paid, so they are not real orders and must stay out
     * of every count on the dashboards.
     *
     * @return string[]
     */
    public static function placedValues(): array
    {
        return [
            self::PROCESSING->value,
            self::CONFIRMED->value,
            self::COURIER_ASSIGNED->value,
            self::SHIPPED->value,
            self::DELIVERED->value,
            self::CANCELED->value,
            self::RETURNED->value,
        ];
    }

    /**
     * Statuses whose money actually counts as revenue: placed orders minus the
     * ones the customer never paid for in the end (cancelled / returned).
     *
     * @return string[]
     */
    public static function revenueValues(): array
    {
        return [
            self::PROCESSING->value,
            self::CONFIRMED->value,
            self::COURIER_ASSIGNED->value,
            self::SHIPPED->value,
            self::DELIVERED->value,
        ];
    }
}
