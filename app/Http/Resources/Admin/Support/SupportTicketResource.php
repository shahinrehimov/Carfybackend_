<?php

namespace App\Http\Resources\Admin\Support;

use App\Services\LangService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @property mixed $id
 * @property mixed $uuid
 * @property string $subject
 * @property mixed $status
 * @property string $opened_by
 * @property int $messages_count
 * @property int $unread_count
 * @property mixed $last_message_at
 * @property mixed $created_at
 * @property mixed $company
 * @property mixed $assignedAdmin
 * @property mixed $messages
 */
class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'uuid'         => $this->uuid,
            'subject'      => $this->subject,
            'status'       => $this->status,
            'status_label' => LangService::instance()
                ->setDefault(Str::title(str_replace('_', ' ', $this->status->value)))
                ->getLang('support_ticket_status_' . $this->status->value),
            'opened_by'    => $this->opened_by,
            'company'      => $this->whenLoaded('company', fn() => [
                'id'         => $this->company->id,
                'store_code' => $this->company->store_code,
            ]),
            'assigned_admin' => $this->whenLoaded('assignedAdmin', fn() => $this->assignedAdmin ? [
                'id'   => $this->assignedAdmin->id,
                'name' => trim(($this->assignedAdmin->name ?? '') . ' ' . ($this->assignedAdmin->surname ?? '')) ?: $this->assignedAdmin->email,
            ] : null),
            'messages_count' => $this->whenCounted('messages'),
            'unread_count'   => $this->whenCounted('unread_count') ?? 0,
            'messages'       => SupportMessageResource::collection($this->whenLoaded('messages')),
            'last_message_at' => $this->last_message_at?->toDateTimeString(),
            'created_at'     => $this->created_at?->toDateTimeString(),
        ];
    }
}
