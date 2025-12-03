<?php

namespace App\Enum;

use App\Services\LangService;
use Illuminate\Support\Str;

enum GenderEnum: string
{
    case MALE = 'male';
    case FEMALE = 'female';

    public static function getList(): array
    {
        $array = [];

        foreach (self::cases() as $case) {
            $array[] = [
                'id' => $case,
                'title' => LangService::instance()
                    ->setDefault(Str::title($case->value))
                    ->getLang('gender_' . $case->value),
            ];
        }

        return $array;
    }
}
