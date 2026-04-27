<?php

namespace App\Http\Resources\Admin\Notifications;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $uuid
 * @property mixed $source
 * @property mixed $event_type
 * @property mixed $status
 * @property mixed $target_type
 * @property mixed $target_filters
 * @property mixed $channels
 * @property mixed $action_type
 * @property mixed $action_id
 * @property mixed $image
 * @property mixed $total_recipients
 * @property mixed $rejection_reason
 * @property mixed $approved_at
 * @property mixed $scheduled_at
 * @property mixed $sent_at
 * @property mixed $created_at
 * @property mixed $translatable
 * @property mixed $sender
 * @property mixed $approvedBy
 */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'source' => $this->source,
            'event_type' => $this->event_type,
            'status' => $this->status,
            'target_type' => $this->target_type,
            'target_filters' => $this->target_filters,
            'channels' => $this->channels,
            'action_type' => $this->action_type,
            'action_id' => $this->action_id,
            'image' => $this->image,
            'total_recipients' => $this->total_recipients,
            'rejection_reason' => $this->rejection_reason,
            'approved_at' => $this->approved_at?->format('d.m.Y H:i'),
            'scheduled_at' => $this->scheduled_at?->format('d.m.Y H:i'),
            'sent_at' => $this->sent_at?->format('d.m.Y H:i'),
            'created_at' => $this->created_at?->format('d.m.Y H:i'),
            'translations' => $this->translatable->groupBy('language_id')->map(fn($items, $langId) => [
                'language_id' => $langId,
                'title' => $items->firstWhere('column', 'title')?->text,
                'body' => $items->firstWhere('column', 'body')?->text,
            ])->values(),
            'sender' => $this->when($this->sender, fn() => [
                'type' => $this->sender_type,
                'name' => $this->sender?->name . ' ' . $this->sender?->surname,
            ]),
            'approved_by' => $this->when($this->approvedBy, fn() => [
                'id' => $this->approvedBy?->id,
                'name' => $this->approvedBy?->name . ' ' . $this->approvedBy?->surname,
            ]),
        ];
    }
}
