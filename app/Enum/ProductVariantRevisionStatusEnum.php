<?php

namespace App\Enum;

enum ProductVariantRevisionStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
