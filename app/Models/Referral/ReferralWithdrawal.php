<?php

namespace App\Models\Referral;

use App\Enum\Referral\ReferralWithdrawalStatusEnum;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralWithdrawal extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'fee',
        'net_amount',
        'card_last4',
        'card_holder_name',
        'status',
        'admin_id',
        'admin_note',
        'requested_at',
        'processed_at',
    ];

    protected $casts = [
        'status'       => ReferralWithdrawalStatusEnum::class,
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
