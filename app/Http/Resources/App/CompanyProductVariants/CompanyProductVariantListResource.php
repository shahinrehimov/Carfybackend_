<?php

namespace App\Http\Resources\App\CompanyProductVariants;

use App\Http\Resources\App\Products\ProductsResource;
use App\Models\CompanyProductVariant;
use App\Services\FitmentSelectionService;
use App\Services\LangService;
use App\Services\StoreAvailabilityService;
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
 * @property mixed       $ratings
 * @property mixed       $basket_quantity
 */
class CompanyProductVariantListResource extends JsonResource
{
    /**
     * Canonical product list item. Every product list in the app/site API
     * (products load, groups, related, basket, wishlist, order items) returns
     * this exact structure, so no key may be conditionally omitted.
     */
    public function toArray(Request $request): array
    {
        $variant = $this->productVariant;
        $basketQuantity = (int) ($this->basket_quantity ?? 0);
        // A discount outside its start/end window no longer changes the price,
        // so its type and value are not shown either.
        $hasDiscount = $this->site_price != $this->site_price_final;

        return [
            // Product variant id. Kept because clients use it as a list key and
            // as the local identity when removing a row. Note it is NOT unique
            // in other-sellers, where several listings share one variant — use
            // `uuid` there.
            'id'            => $variant?->id,
            'uuid'          => $this->uuid,
            'variant_slug'  => $variant?->slug,
            'title'         => $variant?->title,
            'oem_code'      => $variant?->oem_code,
            'hover_image' => $this->listing_hover_image,
            'is_set'      => $variant?->isSet() ?? false,
            // Adding from a list card must open the car selection modal first
            // (options come from the product show endpoint).
            'requires_fitment_selection' => $variant ? FitmentSelectionService::instance()->requires($variant) : false,
            'stock'       => $this->stock,
            // Site prices carry the platform commission; the buyer only ever
            // sees the final number, never the commission itself.
            'price'       => $this->site_price,
            'price_final' => $this->site_price_final,
            'has_discount' => $hasDiscount,
            'discount_type' => $hasDiscount ? $this->discount_type : null,
            'discount_type_title' => $hasDiscount && $this->discount_type
                ? LangService::instance()
                    ->setDefault(Str::title($this->discount_type))
                    ->getLang('discount_types_' . $this->discount_type)
                : null,
            'discount'    => $hasDiscount ? $this->discount : null,
            'in_wishlist' => $this->in_wishlist ?? false,
            'in_basket'   => $this->in_basket ?? false,
            'basket_quantity' => $basketQuantity,
            'price_in_basket' => round($this->site_price * $basketQuantity, 2),
            'price_final_in_basket' => round($this->site_price_final * $basketQuantity, 2),
            'ratings'     => $this->ratings ?? ['average' => 0, 'count' => 0],

            'seller' => [
                'uuid'       => $this->uuid,
                'store_code' => $this->company?->store_code,
                'label'      => $this->company?->getSellerLabel(),
                // Whether the store takes orders right now; `message` explains
                // when it reopens and is null while open.
                'availability' => StoreAvailabilityService::instance()->for($this->company),
                'city'       => $this->relationLoaded('city') && $this->city
                    ? [
                        'id'    => $this->city->id,
                        'title' => $this->city->getLang('title'),
                    ]
                    : null,
                'images'     => $this->relationLoaded('media')
                    ? $this->getMedia('company_product_variant_images')
                        ->map(fn($media) => ['url' => CompanyProductVariant::webUrlForMedia($media)])
                        ->values()
                        ->all()
                    : [],
            ],

            'product' => $variant?->relationLoaded('product') && $variant->product
                ? ProductsResource::make($variant->product)
                : null,
        ];
    }
}
