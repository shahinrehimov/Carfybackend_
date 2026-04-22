<?php

namespace App\Http\Resources\Sellers\Orders;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $shipping = $this->whenLoaded('shippings', fn() => $this->shippings->first());

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'created_at' => $this->created_at?->format('d.m.Y, H:i'),
            'shipping' => $shipping ? $shipping->amount : null,
            'items' => $this->whenLoaded('items', fn() =>
                SellerOrderItemResource::collection($this->items)
            ),
        ];
    }
}
