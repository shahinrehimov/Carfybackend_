<?php

namespace App\Enum;

enum NotificationSourceEnum: string
{
    case SYSTEM = 'system';
    case ADMIN = 'admin';
    case SELLER = 'seller';
}
