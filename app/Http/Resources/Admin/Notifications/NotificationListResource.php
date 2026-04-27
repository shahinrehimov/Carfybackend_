<?php

namespace App\Http\Resources\Admin\Notifications;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $uuid
 * @property mixed $source
 * @property mixed $status
 * @property mixed $target_type
 * @property mixed $channels
 * @property mixed $total_recipients
 * @property mixed $scheduled_at
 * @property mixed $sent_at
 * @property mixed $created_at
 * @property mixed $sender
 */
class NotificationListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->getLang('title'),
            'source' => $this->source,
            'status' => $this->status,
            'target_type' => $this->target_type,
            'channels' => $this->channels,
            'total_recipients' => $this->total_recipients,
            'scheduled_at' => $this->scheduled_at?->format('d.m.Y H:i'),
            'sent_at' => $this->sent_at?->format('d.m.Y H:i'),
            'created_at' => $this->created_at?->format('d.m.Y H:i'),
            'sender' => $this->when($this->sender, fn() => [
                'type' => $this->sender_type,
                'name' => $this->sender?->name . ' ' . $this->sender?->surname,
            ]),
        ];
    }
}
