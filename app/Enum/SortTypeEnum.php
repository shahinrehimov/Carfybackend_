<?php

namespace App\Enum;

use App\Services\LangService;
use Illuminate\Support\Str;

enum SortTypeEnum: string
{
    case ASC  = 'asc';
    case DESC = 'desc';

    public static function getList(): array
    {
        $array = [];

        foreach (self::cases() as $case) {
            $array[] = [
                'id'    => $case->value,
                'title' => LangService::instance()
                    ->setDefault(Str::upper($case->value))
                    ->getLang('sort_type_' . $case->value),
            ];
        }

        return $array;
    }
}
