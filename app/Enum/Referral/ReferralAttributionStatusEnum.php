<?php

namespace App\Enum\Referral;

enum ReferralAttributionStatusEnum: string
{
    case PENDING       = 'pending';
    case ACTIVE        = 'active';
    case FRAUD_BLOCKED = 'fraud_blocked';
}
