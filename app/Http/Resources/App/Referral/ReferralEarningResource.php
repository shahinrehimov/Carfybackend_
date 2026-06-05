<?php

namespace App\Http\Resources\App\Referral;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferralEarningResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'amount'       => round($this->amount / 100, 2),
            'order_amount' => round($this->order_amount / 100, 2),
            'rate'         => $this->rate / 100 . '%',
            'tier'         => $this->tier_at_time,
            'status'       => $this->status,
            'order_uuid'   => $this->order?->uuid,
            'delivered_at' => $this->delivered_at?->toISOString(),
            'available_at' => $this->available_at?->toISOString(),
            'credited_at'  => $this->credited_at?->toISOString(),
        ];
    }
}
