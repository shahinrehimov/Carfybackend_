<?php

namespace App\Models\Referral;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPaymentCard extends Model
{
    protected $fillable = [
        'user_id',
        'fingerprint',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
