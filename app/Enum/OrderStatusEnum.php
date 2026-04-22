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
}
