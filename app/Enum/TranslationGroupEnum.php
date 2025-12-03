<?php

namespace App\Enum;

use Illuminate\Support\Str;

enum TranslationGroupEnum: string
{
    case ALL = 'all';
    case ADMIN = 'admin';
    case APP = 'app';

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
