<?php

namespace App\Http\Resources\App\Referral;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferralMyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code'              => $this['code'],
            'link'              => $this['link'],
            'tier'              => $this['tier'],
            'rate'              => $this['rate'],
            'is_active'         => $this['is_active'],
            'total_referrals'   => $this['total_referrals'],
            'active_referrals'  => $this['active_referrals'],
            'pending_referrals' => $this['pending_referrals'],
            'pending_balance'   => $this['pending_balance'],
            'available_balance' => $this['available_balance'],
            'lifetime_earned'   => $this['lifetime_earned'],
        ];
    }
}
