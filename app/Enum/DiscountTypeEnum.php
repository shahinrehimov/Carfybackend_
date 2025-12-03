<?php

namespace App\Enum;

use App\Services\LangService;
use Illuminate\Support\Str;

enum DiscountTypeEnum: string
{
    case BY_AMOUNT = 'by_amount';
    case BY_PERCENTAGE = 'by_percentage';

    public static function getList(): array
    {
        $array = [];

        foreach (self::cases() as $case) {
            $array[] = [
                'id' => $case,
                'title' => LangService::instance()
                    ->setDefault(Str::title($case->value))
                    ->getLang('discount_types_' . $case->value),
            ];
        }

        return $array;
    }
}
