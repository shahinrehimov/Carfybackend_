<?php

namespace App\Enum;

enum NotificationActionTypeEnum: string
{
    case NONE = 'none';
    case PRODUCT = 'product';
    case ORDER = 'order';
    case CATEGORY = 'category';
    case URL = 'url';
}
