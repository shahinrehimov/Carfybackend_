<?php

namespace App\Http\Resources\App\ProductVariants;

use App\Http\Resources\App\CompanyProductVariants\CompanyProductVariantListResource;
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
 * @property mixed $in_wishlist
 * @property mixed $in_basket
 * @property mixed $basket_quantity
 * @property mixed $ratings
 * @property mixed $title
 * @property mixed $oem_code
 * @property mixed $seller_listing
 * @method getLang(string $string)
 * @method isSet()
 */
class ProductVariantsInBasketResource extends JsonResource
{
    /**
     * Basket / wishlist item.
     *
     * The item is always emitted in the canonical product list structure
     * (see CompanyProductVariantListResource) so that basket, wishlist and
     * products load are interchangeable on the client side. Only the nested
     * `sets` breakdown is specific to this resource.
     *
     * @param Request $request
     * @return array
     */

    public function toArray(Request $request): array
    {
        $sellerListing = $this->seller_listing ?? null;

        $base = $sellerListing
            ? CompanyProductVariantListResource::make($sellerListing)->resolve($request)
            : $this->variantOnlyPayload();

        return $base + [
            'sets' => ProductVariantsInBasketResource::collection($this->whenLoaded('sets')),
        ];
    }

    /**
     * Fallback for variants without a seller listing (set items, legacy rows).
     * Mirrors the canonical structure key for key.
     */
    private function variantOnlyPayload(): array
    {
        $basketQuantity = (int) ($this->basket_quantity ?? 0);

        return [
            'uuid' => $this->uuid,
            'variant_slug' => $this->slug,
            'title' => $this->title,
            'oem_code' => $this->oem_code,
            'hover_image' => $this->relationLoaded('media') ? $this->hover_image : null,
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
            'basket_quantity' => $basketQuantity,
            'price_in_basket' => round($this->price_in_basket, 2),
            'price_final_in_basket' => round($this->price_final_in_basket, 2),
            'ratings' => $this->ratings ?? ['average' => 0, 'count' => 0],

            'seller' => null,

            'product' => $this->relationLoaded('product') && $this->product
                ? ProductsResource::make($this->product)
                : null,
        ];
    }
}
