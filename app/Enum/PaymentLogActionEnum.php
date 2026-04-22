<?php

namespace App\Enum;

use Illuminate\Support\Str;

enum PaymentLogActionEnum: string
{
    case CHECKOUT = 'checkout';
    case CALLBACK = 'callback';
    case CHECK_STATUS = 'check_status';
    case REFUND = 'refund';
    case PARTIAL_REFUND = 'partial_refund';

    public static function getList(): array
    {
        $array = [];

        foreach (self::cases() as $case) {
            $array[] = [
                'id' => $case,
                'title' => Str::title($case->value),
            ];
        }

        return $array;
    }
}
