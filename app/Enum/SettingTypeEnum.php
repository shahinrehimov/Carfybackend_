<?php

namespace App\Enum;

use Illuminate\Support\Str;

enum SettingTypeEnum: string
{
    case STRING = 'string';
    case FILE = 'file';

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
