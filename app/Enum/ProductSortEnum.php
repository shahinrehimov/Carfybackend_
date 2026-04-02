<?php

namespace App\Enum;

use App\Services\LangService;
use Illuminate\Support\Str;

enum ProductSortEnum: string
{
    case PRICE = 'price';
    case TITLE = 'title';
    case DATE  = 'date';

    public static function getList(): array
    {
        $array = [];

        foreach (self::cases() as $case) {
            $array[] = [
                'id'    => $case->value,
                'title' => LangService::instance()
                    ->setDefault(Str::title(str_replace('_', ' ', $case->value)))
                    ->getLang('product_sort_' . $case->value),
            ];
        }

        return $array;
    }
}
