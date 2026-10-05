<?php

namespace App\Http\Resources\App\CompanyProductVariants;

use App\Services\LangService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @property mixed $uuid
 * @property mixed $discount_type
 * @property mixed $discount
 * @property mixed $productVariant
 * @property mixed $company
 * @property float $site_price
 * @property float $site_price_final
 * @property string|null $listing_thumb_image
 * @property int $sellers_count
 * @property float $min_price
 * @property float $max_price
 */
class CompanyProductVariantSuggestionResource extends JsonResource
{
    /**
     * Compact search suggestion row. One row stands for a variant; the listing
     * behind it is the cheapest one, and `sellers` tells whether other stores
     * sell the same variant at other prices.
     */
    public function toArray(Request $request): array
    {
        $variant = $this->productVariant;
        $product = $variant?->product;
        $mainCategory = $product?->mainCategory;
        $parentCategory = $mainCategory?->parent;
        $hasDiscount = $this->site_price != $this->site_price_final;

        return [
            'id'           => $variant?->id,
            'uuid'         => $this->uuid,
            'variant_slug' => $variant?->slug,
            'product_slug' => $product?->slug,
            'title'        => $variant?->title,
            'oem_code'     => $variant?->oem_code,
            'image'        => $this->listing_thumb_image,

            'price'         => $this->site_price,
            'price_final'   => $this->site_price_final,
            'has_discount'  => $hasDiscount,
            'discount_type' => $hasDiscount ? $this->discount_type : null,
            'discount_type_title' => $hasDiscount && $this->discount_type
                ? LangService::instance()
                    ->setDefault(Str::title($this->discount_type))
                    ->getLang('discount_types_' . $this->discount_type)
                : null,
            'discount'      => $hasDiscount ? $this->discount : null,

            'category' => [
                'title'        => $mainCategory?->getLang('title'),
                'slug'         => $mainCategory?->slug,
                'parent_title' => $parentCategory?->getLang('title'),
                'parent_slug'  => $parentCategory?->slug,
            ],

            // Store behind this row (the cheapest one). `store_code` is the
            // `seller` query param of the slug-based product detail URL.
            'seller' => [
                'store_code' => $this->company?->store_code,
                'label'      => $this->company?->getSellerLabel(),
            ],

            // Final prices across every store selling this variant. When
            // `has_other_prices` is true the client can say "N stores, from X".
            'sellers' => [
                'count'            => $this->sellers_count,
                'min_price'        => $this->min_price,
                'max_price'        => $this->max_price,
                'has_other_prices' => $this->min_price != $this->max_price,
            ],
        ];
    }
}
