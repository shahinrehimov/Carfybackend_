<?php

namespace App\Enum;

use Illuminate\Support\Str;

enum UserOtpProvidersEnum: string
{
    case SMS = 'sms';
    case EMAIL = 'email';

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
