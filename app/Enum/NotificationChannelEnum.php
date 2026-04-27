<?php

namespace App\Enum;

enum NotificationChannelEnum: string
{
    case IN_APP = 'in_app';
    case PUSH = 'push';
    case SMS = 'sms';
    case EMAIL = 'email';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function defaults(): array
    {
        return [self::IN_APP->value, self::PUSH->value];
    }
}
