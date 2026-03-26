<?php

namespace App\Http\Resources\App\ProductVariants;

use App\Http\Resources\Admin\BrandModels\BrandModelsListResource;
use App\Http\Resources\Admin\Cities\CitiesListResource;
use App\Http\Resources\App\Products\ProductResource;
use App\Models\CompanyProductVariant;
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
 * @property mixed $main_image
 * @property mixed $images
 * @property mixed $related_products
 * @property mixed $in_wishlist
 * @property mixed $in_basket
 * @property mixed $ratings
 * @property mixed $title
 * @property mixed $year
 * @property mixed $oem_code
 * @method getLang(string $string)
 * @method isSet()
 */
class ProductVariantResource extends JsonResource
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
            'main_image' => $this->whenLoaded('media', function () {
                return $this->main_image;
            }),
            'hover_image' => $this->whenLoaded('media', function () {
                return $this->hover_image;
            }),
            'images' => $this->whenLoaded('media', function () {
                return $this->images['urls'] ?? [];
            }),
            'city' => CitiesListResource::make($this->whenLoaded('city')),
            'brand_models' => BrandModelsListResource::collection($this->whenLoaded('brandModels')),
            'product' => ProductResource::make($this->whenLoaded('product')),
            'is_set' => $this->isSet(),
            'sets' => ProductVariantsResource::collection($this->whenLoaded('sets')),
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
            'year' => $this->year,
            'in_wishlist' => $this->in_wishlist ?? false,
            'in_basket' => $this->in_basket ?? false,
            'options' => ProductVariantOptionsResource::collection($this->whenLoaded('options')),
            'ratings' => $this->whenNotNull($this->ratings),
            'related_products' => ProductVariantsResource::collection($this->whenNotNull($this->related_products)),

            // Seller data (price/stock/discount already overridden at top level via setRawAttributes)
            'seller' => $this->when(isset($this->seller_listing), function () {
                if (!$this->seller_listing) return null;
                $sl = $this->seller_listing;
                return [
                    'uuid'         => $sl->uuid,
                    'seller_label' => $sl->company?->getSellerLabel(),
                    'city'         => $sl->relationLoaded('city')
                        ? CitiesListResource::make($sl->city)
                        : null,
                    'images'       => $sl->relationLoaded('media')
                        ? $sl->getMedia('company_product_variant_images')->map(fn($m) => ['url' => $m->getUrl()])
                        : [],
                    'gallery'      => $sl->listing_gallery,
                ];
            }),
            'sellers_count' => $this->whenNotNull($this->sellers_count),

            // Other sellers
            'other_sellers' => $this->when(isset($this->other_sellers), function () {
                return $this->other_sellers->map(fn(CompanyProductVariant $cpv) => [
                    'uuid'          => $cpv->uuid,
                    'seller_label'  => $cpv->company?->getSellerLabel(),
                    'price'         => $cpv->price,
                    'price_final'   => $cpv->price_final,
                    'has_discount'  => $cpv->price != $cpv->price_final,
                    'discount_type' => $cpv->discount_type,
                    'discount'      => $cpv->discount,
                    'stock'         => $cpv->stock,
                    'city'          => $cpv->relationLoaded('city') ? CitiesListResource::make($cpv->city) : null,
                    'images'        => $cpv->relationLoaded('media')
                        ? $cpv->getMedia('company_product_variant_images')->map(fn($m) => ['url' => $m->getUrl()])
                        : [],
                ]);
            }),
            'other_sellers_total' => $this->whenNotNull($this->other_sellers_total),
        ];
    }
}
