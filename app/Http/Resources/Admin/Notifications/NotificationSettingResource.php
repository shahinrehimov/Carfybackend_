<?php

namespace App\Http\Resources\Admin\Notifications;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $event_type
 * @property mixed $is_active
 * @property mixed $channels
 */
class NotificationSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'is_active' => $this->is_active,
            'channels' => $this->channels,
        ];
    }
}
