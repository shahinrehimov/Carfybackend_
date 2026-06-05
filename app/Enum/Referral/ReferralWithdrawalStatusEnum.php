<?php

namespace App\Enum\Referral;

enum ReferralWithdrawalStatusEnum: string
{
    case PENDING   = 'pending';
    case APPROVED  = 'approved';
    case REJECTED  = 'rejected';
    case PAID      = 'paid';
    case CANCELLED = 'cancelled';
}
