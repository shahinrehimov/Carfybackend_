<?php

namespace App\Http\Resources\App\ProductVariants;

use App\Http\Resources\App\Products\ProductsResource;
use App\Services\LangService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @property mixed $id
 * @property mixed $uuid
 * @property mixed $slug
 * @property mixed $hover_image
 * @property mixed $stock
 * @property mixed $price
 * @property mixed $price_final
 * @property mixed $discount_type
 * @property mixed $discount
 * @property mixed $mainCategory
 * @property mixed $in_wishlist
 * @property mixed $in_basket
 * @property mixed $basket_quantity
 * @property mixed $title
 * @property mixed $oem_code
 * @property mixed $seller_listing
 * @method getLang(string $string)
 * @method isSet()
 */
class ProductVariantsResource extends JsonResource
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
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'oem_code' => $this->oem_code,
            'title' => $this->title,
            'hover_image' => $this->whenLoaded('media', function () {
                return $this->hover_image;
            }),
            'is_set' => $this->isSet(),
            'stock' => $this->stock,
            'price' => $this->price,
            'price_final' => $this->price_final,
            'has_discount' => $this->price != $this->price_final,
            'discount_type' => $this->discount_type,
            'discount_type_title' => $this->discount_type
                ? LangService::instance()
                    ->setDefault(Str::title($this->discount_type))
                    ->getLang('discount_types_' . $this->discount_type)
                : null,
            'discount' => $this->discount,
            'in_wishlist' => $this->in_wishlist ?? false,
            'in_basket' => $this->in_basket ?? false,
            'basket_quantity' => $this->whenNotNull($this->basket_quantity),

            'seller' => $this->seller_listing ? [
                'uuid'         => $this->seller_listing->uuid,
                'seller_label' => $this->seller_listing->company?->getSellerLabel(),
                'city'         => $this->seller_listing->relationLoaded('city') && $this->seller_listing->city ? [
                    'id'    => $this->seller_listing->city->id,
                    'title' => $this->seller_listing->city->getLang('title'),
                ] : null,
            ] : null,

            'product' => ProductsResource::make($this->whenLoaded('product')),
            'sellers_count' => $this->whenCounted('companyListings'),
        ];
    }
}
