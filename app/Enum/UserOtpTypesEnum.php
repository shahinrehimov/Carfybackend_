<?php

namespace App\Enum;

use Illuminate\Support\Str;

enum UserOtpTypesEnum: string
{
    case REGISTER = 'register';
    case CHANGE_PASSWORD = 'change_password';
    case LOGIN = 'login';

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
