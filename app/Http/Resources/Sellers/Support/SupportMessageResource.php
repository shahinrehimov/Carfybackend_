<?php

namespace App\Http\Resources\Sellers\Support;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property string $sender_type
 * @property string $body
 * @property bool $is_read_by_seller
 * @property mixed $created_at
 * @property mixed $media
 * @property mixed $sender
 */
class SupportMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'body'        => $this->body,
            'sender_type' => $this->sender_type === Admin::class ? 'admin' : 'seller',
            'sender_name' => $this->sender_type === Admin::class
                ? 'Carfy Dəstəyi'
                : ($this->whenLoaded('sender', fn() => $this->sender?->username ?? $this->sender?->email, null)),
            'files'       => $this->whenLoaded('media', fn() => $this->getMedia('support_files')->map(fn($m) => [
                'id'  => $m->id,
                'url' => $m->getFullUrl(),
                'name' => $m->file_name,
                'size' => $m->size,
            ])),
            'is_read_by_seller' => $this->is_read_by_seller,
            'created_at'  => $this->created_at?->toDateTimeString(),
        ];
    }
}
