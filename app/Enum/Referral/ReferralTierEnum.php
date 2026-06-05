<?php

namespace App\Enum\Referral;

enum ReferralTierEnum: string
{
    case BRONZE   = 'bronze';
    case SILVER   = 'silver';
    case GOLD     = 'gold';
    case PLATINUM = 'platinum';

    public function rate(): int
    {
        return match($this) {
            self::BRONZE   => 50,   // 0.5% in basis points
            self::SILVER   => 100,  // 1.0%
            self::GOLD     => 150,  // 1.5%
            self::PLATINUM => 200,  // 2.0%
        };
    }

    public static function fromActiveCount(int $count): self
    {
        return match(true) {
            $count >= 31 => self::PLATINUM,
            $count >= 21 => self::GOLD,
            $count >= 11 => self::SILVER,
            default      => self::BRONZE,
        };
    }
}
