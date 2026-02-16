<?php

namespace App\Http\Resources\App\ProductVariants;

use App\Http\Resources\Admin\BrandModels\BrandModelsListResource;
use App\Http\Resources\Admin\Cities\CitiesListResource;
use App\Http\Resources\App\Products\ProductResource;
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
            'brand_model' => BrandModelsListResource::make($this->whenLoaded('brandModel')),
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
        ];
    }
}
