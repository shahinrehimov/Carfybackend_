<?php

namespace App\Http\Resources\App\Orders;

use App\Http\Resources\App\CompanyProductVariants\CompanyProductVariantListResource;
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
    public function toArray(Request $request): array
    {
        $cpv = $this->whenLoaded('companyProductVariant', fn() => $this->companyProductVariant);
        $pv  = $this->whenLoaded('productVariant', fn() => $this->productVariant);

        // Make listing_hover_image cascade work without extra queries:
        // CPV already has media loaded; attach the PV so Level 2/3 fallback works.
        if ($cpv instanceof \App\Models\CompanyProductVariant &&
            $pv  instanceof \App\Models\ProductVariant &&
            !$cpv->relationLoaded('productVariant')
        ) {
            $cpv->setRelation('productVariant', $pv);
        }

        return [
            'quantity'     => $this->quantity,
            'amount'       => $this->amount,
            'amount_final' => $this->amount_final,
            'product'      => $cpv instanceof \App\Models\CompanyProductVariant
                ? CompanyProductVariantListResource::make($cpv)
                : null,
        ];
    }
}
