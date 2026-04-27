<?php

namespace App\Models;

use App\Enum\NotificationEventTypeEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $event_type
 * @property bool $is_active
 * @property array $channels
 */
class NotificationTypeSetting extends Model
{
    protected $fillable = [
        'event_type',
        'is_active',
        'channels',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'channels' => 'array',
    ];

    public function getEventTypeEnum(): NotificationEventTypeEnum
    {
        return NotificationEventTypeEnum::from($this->event_type);
    }
}
