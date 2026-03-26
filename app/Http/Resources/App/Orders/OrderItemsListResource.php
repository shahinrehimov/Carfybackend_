<?php

namespace App\Http\Resources\App\Orders;

use App\Http\Resources\App\ProductVariants\ProductVariantsResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $quantity
 * @property mixed $amount
 * @property mixed $amount_final
 */
class OrderItemsListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_variant' => ProductVariantsResource::make($this->whenLoaded('productVariant')),
            'quantity' => $this->quantity,
            'amount' => $this->amount,
            'amount_final' => $this->amount_final,
            'seller' => $this->whenLoaded('companyProductVariant', function () {
                $cpv = $this->companyProductVariant;
                return [
                    'uuid'         => $cpv->uuid,
                    'seller_label' => $cpv->company?->getSellerLabel(),
                    'city'         => $cpv->relationLoaded('city') && $cpv->city ? [
                        'id'    => $cpv->city->id,
                        'title' => $cpv->city->getLang('title'),
                    ] : null,
                    'images'       => $cpv->relationLoaded('media')
                        ? $cpv->getMedia('company_product_variant_images')->map(fn($m) => ['url' => $m->getUrl()])
                        : [],
                ];
            }),
        ];
    }
}
