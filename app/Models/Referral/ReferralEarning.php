<?php

namespace App\Models\Referral;

use App\Enum\Referral\ReferralEarningStatusEnum;
use App\Enum\Referral\ReferralTierEnum;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralEarning extends Model
{
    protected $fillable = [
        'referrer_id',
        'referred_id',
        'order_id',
        'tier_at_time',
        'rate',
        'order_amount',
        'amount',
        'status',
        'delivered_at',
        'available_at',
        'credited_at',
        'refunded_at',
    ];

    protected $casts = [
        'status'       => ReferralEarningStatusEnum::class,
        'tier_at_time' => ReferralTierEnum::class,
        'delivered_at' => 'datetime',
        'available_at' => 'datetime',
        'credited_at'  => 'datetime',
        'refunded_at'  => 'datetime',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function amountInAzn(): float
    {
        return round($this->amount / 100, 2);
    }
}
