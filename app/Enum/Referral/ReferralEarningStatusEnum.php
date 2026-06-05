<?php

namespace App\Enum\Referral;

enum ReferralEarningStatusEnum: string
{
    case PENDING     = 'pending';
    case AVAILABLE   = 'available';
    case REFUNDED    = 'refunded';
    case CANCELLED   = 'cancelled';
    case FRAUD_HOLD  = 'fraud_hold';
}
