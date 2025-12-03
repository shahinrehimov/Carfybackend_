<?php

namespace App\Models;

use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserOtpCode extends Model
{
    use SoftDeletes, ActionBy, ActionUser;

    protected $fillable = [
        'user_id',
        'provider', // UserOtpProvidersEnum
        'type', // UserOtpTypesEnum
        'code',
        'attempts',
        'used_at',
    ];

    public const MAX_ATTEMPTS = 3;
    public const EXPIRED_MINUTE = 3;

    protected $casts = [
        'used_at' => 'datetime',
        'attempts' => 'integer',
        'code' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
