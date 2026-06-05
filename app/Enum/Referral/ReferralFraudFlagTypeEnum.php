<?php

namespace App\Enum\Referral;

enum ReferralFraudFlagTypeEnum: string
{
    case CARD     = 'card';
    case PHONE    = 'phone';
    case DEVICE   = 'device';
    case IP       = 'ip';
    case VELOCITY = 'velocity';
}
