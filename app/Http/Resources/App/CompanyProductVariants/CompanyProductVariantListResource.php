<?php

namespace App\Http\Resources\App\CompanyProductVariants;

use App\Http\Resources\App\Products\ProductsResource;
use App\Services\LangService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @property mixed       $id
 * @property mixed       $uuid
 * @property mixed       $price
 * @property mixed       $price_final
 * @property mixed       $discount_type
 * @property mixed       $discount
 * @property mixed       $stock
 * @property bool        $in_wishlist
 * @property bool        $in_basket
 * @property mixed       $company
 * @property mixed       $city
 * @property mixed       $productVariant
 */
class CompanyProductVariantListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variant = $this->productVariant;

        return [
            'uuid'        => $this->uuid,
            'title'       => $variant?->title,
            'oem_code'    => $variant?->oem_code,
            'hover_image' => $this->listing_hover_image,
            'is_set'      => $variant?->isSet() ?? false,
            'stock'       => $this->stock,
            'price'       => $this->price,
            'price_final' => $this->price_final,
            'has_discount' => $this->price != $this->price_final,
            'discount_type' => $this->discount_type,
            'discount_type_title' => $this->discount_type
                ? LangService::instance()
                    ->setDefault(Str::title($this->discount_type))
                    ->getLang('discount_types_' . $this->discount_type)
                : null,
            'discount'    => $this->discount,
            'in_wishlist' => $this->in_wishlist ?? false,
            'in_basket'   => $this->in_basket ?? false,

            'seller' => [
                'uuid'  => $this->uuid,
                'label' => $this->company?->getSellerLabel(),
                'city'  => $this->when(
                    $this->relationLoaded('city') && $this->city,
                    fn() => [
                        'id'    => $this->city->id,
                        'title' => $this->city->getLang('title'),
                    ]
                ),
            ],

            'product' => $this->when(
                $variant?->relationLoaded('product'),
                fn() => ProductsResource::make($variant->product)
            ),
        ];
    }
}
