<?php

namespace App\Enum;

use App\Services\LangService;
use Illuminate\Support\Str;

enum ProductSizeCategoryEnum: string
{
    case SMALL = 'small';
    case MEDIUM = 'medium';
    case LARGE = 'large';

    public static function getList(): array
    {
        $array = [];

        foreach (self::cases() as $case) {
            $array[] = [
                'id' => $case,
                'title' => LangService::instance()
                    ->setDefault(Str::title($case->value))
                    ->getLang('size_category_' . $case->value),
            ];
        }

        return $array;
    }
}
