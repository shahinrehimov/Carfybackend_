<?php

namespace App\Models\Referral;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralActivityStatus extends Model
{
    public $primaryKey = 'user_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $table = 'referral_activity_status';

    protected $fillable = [
        'user_id',
        'is_active',
        'last_order_at',
        'new_referrals_last_30d',
        'last_check_at',
        'became_passive_at',
        'updated_at',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'last_order_at'     => 'datetime',
        'last_check_at'     => 'datetime',
        'became_passive_at' => 'datetime',
        'updated_at'        => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
