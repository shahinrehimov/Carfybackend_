<?php

namespace App\Enum;

use App\Services\LangService;
use Illuminate\Support\Str;

enum ProductSearchStatusEnum: string
{
    case ON_SALE = 'on_sale';
    case IN_STOCK = 'in_stock';
    case ON_BACKORDER = 'on_backorder';

    public static function getList(): array
    {
        $array = [];

        foreach (self::cases() as $case) {
            $array[] = [
                'id' => $case,
                'title' => LangService::instance()
                    ->setDefault(Str::title($case->value))
                    ->getLang('products_search_statuses' . $case->value),
            ];
        }

        return $array;
    }
}
