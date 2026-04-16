<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $advertisement_id
 * @property int|null $user_id
 * @property string|null $guest_identifier
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
class AdvertisementClick extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'advertisement_id',
        'user_id',
        'guest_identifier',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function advertisement(): BelongsTo
    {
        return $this->belongsTo(Advertisement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
