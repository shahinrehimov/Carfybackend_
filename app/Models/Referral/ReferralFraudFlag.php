<?php

namespace App\Models\Referral;

use App\Enum\Referral\ReferralFraudFlagTypeEnum;
use App\Enum\Referral\ReferralFraudSeverityEnum;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralFraudFlag extends Model
{
    protected $fillable = [
        'user_id',
        'matched_with_user_id',
        'flag_type',
        'severity',
        'resolved',
        'resolved_by',
        'resolution',
        'resolved_at',
    ];

    protected $casts = [
        'flag_type'   => ReferralFraudFlagTypeEnum::class,
        'severity'    => ReferralFraudSeverityEnum::class,
        'resolved'    => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matchedWithUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_with_user_id');
    }

    public function resolvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'resolved_by');
    }

    public function isHardBlock(): bool
    {
        return $this->severity === ReferralFraudSeverityEnum::HARD_BLOCK;
    }
}
