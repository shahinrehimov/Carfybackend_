<?php

namespace App\Enum;

enum AdvertisementPlatformEnum: string
{
    case SITE = 'site';
    case APP = 'app';
    case BOTH = 'both';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
