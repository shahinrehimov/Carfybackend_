<?php

namespace App\Http\Resources\App\Notifications;

use App\Models\NotificationRecipient;
use App\Services\LangService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $uuid
 * @property mixed $action_type
 * @property mixed $action_id
 * @property mixed $image
 * @property mixed $created_at
 * @property mixed $recipients
 */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = auth()->user();

        /** @var NotificationRecipient|null $recipient */
        $recipient = $this->recipients
            ->where('user_id', $user?->id)
            ->first();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->getLang('title'),
            'body' => $this->getLang('body'),
            'image' => $this->image,
            'action_type' => $this->action_type,
            'action_id' => $this->action_id,
            'is_read' => $recipient?->read_at !== null,
            'date' => $this->created_at?->format('d.m.Y H:i'),
            'date_raw' => $this->created_at,
        ];
    }
}
