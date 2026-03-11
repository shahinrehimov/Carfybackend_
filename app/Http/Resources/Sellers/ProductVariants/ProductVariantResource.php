<?php

namespace App\Http\Resources\Sellers\ProductVariants;

use App\Http\Resources\Admin\BrandModels\BrandModelsListResource;
use App\Http\Resources\Admin\Cities\CitiesListResource;
use App\Http\Resources\Admin\Groups\GroupsListResource;
use App\Http\Resources\Sellers\Products\ProductsListResource;
use App\Services\LangService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @property mixed $id
 * @property mixed $translations
 * @property mixed $image
 * @property mixed $uuid
 * @property mixed $sort
 * @property mixed $slug
 * @property mixed $main_image
 * @property mixed $hover_image
 * @property mixed $images
 * @property mixed $brand_id
 * @property mixed $stock
 * @property mixed $price
 * @property mixed $price_final
 * @property mixed $discount_type
 * @property mixed $discount
 * @property mixed $discount_start_at
 * @property mixed $discount_end_at
 * @property mixed $approval_status
 * @property mixed $approval_status_message
 * @property mixed $year
 * @property mixed $oem_code
 * @method isActive()
 * @method isSet()
 * @method isDefault()
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
            'main_image' => $this->whenLoaded('media', function () {
                return $this->main_image;
            }),
            'hover_image' => $this->whenLoaded('media', function () {
                return $this->hover_image;
            }),
            'images' => $this->whenLoaded('media', function () {
                return $this->images;
            }),
            'is_set' => $this->isSet(),
            'sets' => ProductVariantsListResource::collection($this->whenLoaded('sets')),
            'groups' => GroupsListResource::collection($this->whenLoaded('groups')),
            'options' => ProductVariantOptionsResource::collection($this->whenLoaded('options')),
            'stock' => $this->stock,
            'price' => $this->price,
            'price_final' => $this->price_final,
            'discount_type' => $this->discount_type,
            'discount' => $this->discount,
            'discount_start_at' => $this->discount_start_at?->toDatetimeString(),
            'discount_end_at' => $this->discount_end_at?->toDatetimeString(),
            'city' => CitiesListResource::make($this->whenLoaded('city')),
            'brand_model' => BrandModelsListResource::make($this->whenLoaded('brandModel')),
            'year' => $this->year,
            'translations' => $this->translations,
            'is_default' => $this->isDefault(),
            'is_active' => $this->isActive(),
            'product' => ProductsListResource::make($this->whenLoaded('product')),
            'approval_status' => $this->approval_status,
            'approval_status_label' => LangService::instance()
                ->setDefault(Str::title($this->approval_status))
                ->getLang('product_variant_approval_status_' . $this->approval_status),
            'approval_status_message' => $this->approval_status_message,
        ];
    }
}
