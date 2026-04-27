<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $notification_id
 * @property int $user_id
 * @property \Carbon\Carbon|null $read_at
 * @property \Carbon\Carbon|null $in_app_deleted_at
 * @property \Carbon\Carbon|null $push_sent_at
 * @property \Carbon\Carbon|null $push_failed_at
 * @property string|null $push_error
 * @property \Carbon\Carbon|null $sms_sent_at
 * @property \Carbon\Carbon|null $sms_failed_at
 * @property string|null $sms_error
 * @property \Carbon\Carbon|null $email_sent_at
 * @property \Carbon\Carbon|null $email_failed_at
 * @property string|null $email_error
 */
class NotificationRecipient extends Model
{
    protected $fillable = [
        'notification_id',
        'user_id',
        'read_at',
        'in_app_deleted_at',
        'push_sent_at',
        'push_failed_at',
        'push_error',
        'sms_sent_at',
        'sms_failed_at',
        'sms_error',
        'email_sent_at',
        'email_failed_at',
        'email_error',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'in_app_deleted_at' => 'datetime',
        'push_sent_at' => 'datetime',
        'push_failed_at' => 'datetime',
        'sms_sent_at' => 'datetime',
        'sms_failed_at' => 'datetime',
        'email_sent_at' => 'datetime',
        'email_failed_at' => 'datetime',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
