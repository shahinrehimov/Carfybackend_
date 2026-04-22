<?php

namespace App\Http\Resources\Sellers\Orders;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'cancel_reason' => $this->cancel_reason,
            'quantity' => $this->quantity,
            'amount' => $this->amount,
            'amount_final' => $this->amount_final,
            'is_set' => $this->is_set,
            'sets' => $this->sets,
            'product_variant' => $this->whenLoaded('productVariant', fn() => [
                'id' => $this->productVariant?->id,
                'title' => $this->productVariant?->title,
            ]),
            'company_product_variant' => $this->whenLoaded('companyProductVariant', fn() => [
                'id' => $this->companyProductVariant?->id,
                'uuid' => $this->companyProductVariant?->uuid,
            ]),
            'status_history' => $this->whenLoaded('itemStatuses', fn() =>
                $this->itemStatuses->map(fn($s) => [
                    'status' => $s->status,
                    'date' => $s->created_at?->format('d.m.Y, H:i'),
                ])
            ),
        ];
    }
}
