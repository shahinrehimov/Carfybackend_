<?php

namespace App\Http\Resources\Sellers\Orders;

use App\Services\LangService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class SellerOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_label' => LangService::instance()
                ->setDefault(Str::title($this->status->value))
                ->getLang('order_item_status_' . $this->status->value),
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
