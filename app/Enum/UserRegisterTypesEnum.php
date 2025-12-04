<?php

namespace App\Enum;

use App\Services\LangService;
use Illuminate\Support\Str;

enum UserRegisterTypesEnum: string
{
    case EMAIL = 'email';
    case PHONE = 'phone';
    case GOOGLE = 'google';

    public static function getList(): array
    {
        $array = [];

        foreach (self::cases() as $case) {
            $array[] = [
                'id' => $case,
                'title' => LangService::instance()
                    ->setDefault(Str::title($case->value))
                    ->getLang('user_register_types_' . $case->value),
            ];
        }

        return $array;
    }
}
