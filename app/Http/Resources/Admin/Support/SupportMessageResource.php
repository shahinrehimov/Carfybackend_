<?php

namespace App\Http\Resources\Admin\Support;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property string $sender_type
 * @property string $body
 * @property bool $is_read_by_seller
 * @property bool $is_read_by_admin
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
            'sender_name' => $this->whenLoaded('sender', function () {
                if ($this->sender_type === Admin::class) {
                    return trim(($this->sender->name ?? '') . ' ' . ($this->sender->surname ?? '')) ?: $this->sender->email;
                }
                return $this->sender->username ?? $this->sender->email;
            }, null),
            'files'       => $this->whenLoaded('media', fn() => $this->getMedia('support_files')->map(fn($m) => [
                'id'   => $m->id,
                'url'  => $m->getFullUrl(),
                'name' => $m->name . '.' . $m->extension,
                'size' => $m->size,
            ])),
            'is_read_by_seller' => $this->is_read_by_seller,
            'is_read_by_admin'  => $this->is_read_by_admin,
            'created_at'  => $this->created_at?->toDateTimeString(),
        ];
    }
}
