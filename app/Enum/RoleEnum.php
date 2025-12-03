<?php

namespace App\Enum;

use Illuminate\Support\Str;

enum RoleEnum: string
{
    case ADMIN = 'Admin';
    case MARKETING = 'Marketing';

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
