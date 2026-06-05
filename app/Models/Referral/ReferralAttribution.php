<?php

namespace App\Models\Referral;

use App\Enum\Referral\ReferralAttributionStatusEnum;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralAttribution extends Model
{
    protected $fillable = [
        'referrer_id',
        'referred_id',
        'status',
        'first_qualifying_order_id',
        'activated_at',
    ];

    protected $casts = [
        'status'       => ReferralAttributionStatusEnum::class,
        'activated_at' => 'datetime',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_id');
    }

    public function firstQualifyingOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'first_qualifying_order_id');
    }

    public function isActive(): bool
    {
        return $this->status === ReferralAttributionStatusEnum::ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === ReferralAttributionStatusEnum::PENDING;
    }
}
