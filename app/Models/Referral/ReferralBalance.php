<?php

namespace App\Models\Referral;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralBalance extends Model
{
    public $primaryKey = 'user_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'pending_balance',
        'available_balance',
        'lifetime_earned',
        'lifetime_withdrawn',
        'lifetime_spent_on_platform',
        'updated_at',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pendingInAzn(): float
    {
        return round($this->pending_balance / 100, 2);
    }

    public function availableInAzn(): float
    {
        return round($this->available_balance / 100, 2);
    }
}
