<?php

namespace App\Http\Resources\Sellers\Products;

use App\Services\FitmentPresenter;
use App\Http\Resources\Admin\Categories\CategoriesListResource;
use App\Http\Resources\Admin\Groups\GroupsListResource;
use App\Http\Resources\Sellers\ProductVariants\ProductVariantsListResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
 * @property mixed $stock
 * @property mixed $price
 * @property mixed $price_final
 * @property mixed $discount_type
 * @property mixed $discount
 * @property mixed $discount_start_at
 * @property mixed $discount_end_at
 * @method isActive()
 * @method isSet()
 */
class ProductResource extends JsonResource
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
            // Brand moved onto the variants' fitment rules, so a product can now
            // span several. `brand` stays for existing clients and answers only
            // when the fitments agree on one.
            'brand' => FitmentPresenter::instance()->singleBrandForProduct($this->resource),
            'brands' => FitmentPresenter::instance()->brandsForProduct($this->resource),
            'is_universal' => FitmentPresenter::instance()->isUniversalProduct($this->resource),
            'main_category' => CategoriesListResource::make($this->whenLoaded('mainCategory')),
            'categories' => CategoriesListResource::collection($this->whenLoaded('categories')),
            'groups' => GroupsListResource::collection($this->whenLoaded('groups')),
            'variants_count' => $this->whenCounted('variants'),
            'variants' => ProductVariantsListResource::collection($this->whenLoaded('variants')),
            'translations' => $this->translations,
            'is_active' => $this->isActive(),
        ];
    }
}
