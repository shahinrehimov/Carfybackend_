<?php

namespace App\Enum;

use App\Services\LangService;
use Illuminate\Support\Str;

enum OrderItemStatusEnum: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case READY_FOR_DELIVERY = 'ready_for_delivery';
    case CANCELED = 'canceled';
    case HANDED_TO_COURIER = 'handed_to_courier';

    public static function getList(): array
    {
        $array = [];

        foreach (self::cases() as $case) {
            $array[] = [
                'id' => $case,
                'title' => LangService::instance()
                    ->setDefault(Str::title(str_replace('_', ' ', $case->value)))
                    ->getLang('order_item_status_' . $case->value),
            ];
        }

        return $array;
    }
}
