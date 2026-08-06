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
        'user_id', // nullable
        'receiver', // phone or email. use when user_id is null
        'provider', // UserOtpProvidersEnum
        'type', // UserOtpTypesEnum
        'code',
        'attempts',
        'used_at',
        'confirmation_token',
        'confirmation_token_used_at',
    ];

    public const MAX_ATTEMPTS = 3;
    public const EXPIRED_MINUTE = 3;

    /**
     * Lifetime of a freshly generated code, for clients driving a countdown.
     */
    public static function expiresInSeconds(): int
    {
        return self::EXPIRED_MINUTE * 60;
    }

    protected $casts = [
        'used_at' => 'datetime',
        'confirmation_token_used_at' => 'datetime',
        'attempts' => 'integer',
        'code' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
