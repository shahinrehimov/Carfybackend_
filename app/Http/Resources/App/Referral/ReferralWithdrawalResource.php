<?php

namespace App\Http\Resources\App\Referral;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferralWithdrawalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'amount'           => round($this->amount / 100, 2),
            'fee'              => round($this->fee / 100, 2),
            'net_amount'       => round($this->net_amount / 100, 2),
            'card_last4'       => $this->card_last4,
            'card_holder_name' => $this->card_holder_name,
            'status'           => $this->status,
            'admin_note'       => $this->admin_note,
            'requested_at'     => $this->requested_at?->toISOString(),
            'processed_at'     => $this->processed_at?->toISOString(),
        ];
    }
}
