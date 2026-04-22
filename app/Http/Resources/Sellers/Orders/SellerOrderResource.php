<?php

namespace App\Http\Resources\Sellers\Orders;

use App\Services\LangService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class SellerOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $shipping = $this->whenLoaded('shippings', fn() => $this->shippings->first());

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status,
            'status_label' => LangService::instance()
                ->setDefault(Str::title($this->status->value))
                ->getLang('order_status_' . $this->status->value),
            'payment_status' => $this->payment_status,
            'payment_status_label' => LangService::instance()
                ->setDefault(Str::title($this->payment_status->value))
                ->getLang('order_payment_status_' . $this->payment_status->value),
            'created_at' => $this->created_at?->format('d.m.Y, H:i'),
            'shipping' => $shipping ? $shipping->amount : null,
            'items' => $this->whenLoaded('items', fn() =>
                SellerOrderItemResource::collection($this->items)
            ),
        ];
    }
}
