<?php

namespace App\Enum;

enum NotificationTargetTypeEnum: string
{
    case INDIVIDUAL = 'individual';
    case BULK = 'bulk';
}
