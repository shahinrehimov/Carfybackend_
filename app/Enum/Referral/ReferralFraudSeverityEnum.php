<?php

namespace App\Enum\Referral;

enum ReferralFraudSeverityEnum: string
{
    case HARD_BLOCK = 'hard_block';
    case SOFT_FLAG  = 'soft_flag';
}
