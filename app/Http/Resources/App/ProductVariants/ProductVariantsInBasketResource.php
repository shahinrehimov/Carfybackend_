<?php

namespace App\Http\Resources\App\ProductVariants;

use App\Http\Resources\App\Products\ProductsInBasketResource;
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
 * @property mixed $in_wishlist
 * @property mixed $in_basket
 * @property mixed $basket_quantity
 * @property mixed $price_in_basket
 * @property mixed $price_final_in_basket
 * @property mixed $title
 * @property mixed $oem_code
 * @property mixed $seller_listing
 * @method getLang(string $string)
 * @method isSet()
 */
class ProductVariantsInBasketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */

    public function toArray(Request $request): array
    {
        $sellerListing = $this->seller_listing ?? null;

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
            'price_in_basket' => $this->price_in_basket,
            'price_final_in_basket' => $this->price_final_in_basket,

            'seller' => $sellerListing ? [
                'uuid'         => $sellerListing->uuid,
                'seller_label' => $sellerListing->company?->getSellerLabel(),
                'city'         => $sellerListing->relationLoaded('city') && $sellerListing->city ? [
                    'id'    => $sellerListing->city->id,
                    'title' => $sellerListing->city->getLang('title'),
                ] : null,
                'images'       => $sellerListing->relationLoaded('media')
                    ? $sellerListing->getMedia('company_product_variant_images')->map(fn($m) => ['url' => $m->getUrl()])
                    : [],
            ] : null,

            'product' => ProductsInBasketResource::make($this->whenLoaded('product')),

            'sets' => ProductVariantsInBasketResource::collection($this->whenLoaded('sets')),
        ];
    }
}
