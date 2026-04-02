<?php

namespace App\Http\Resources\Admin\ContactMessages;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactMessagesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'full_name'    => $this->full_name,
            'email'        => $this->email,
            'message'      => $this->message,
            'is_read'      => $this->is_read,
            'created_date' => $this->created_at?->toDateTimeString(),
        ];
    }
}
