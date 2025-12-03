<?php

namespace App\Http\Resources\App\Products;

use App\Http\Resources\Admin\Brands\BrandsListResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $uuid
 * @property mixed $slug
 * @property mixed $hover_image
 * @property mixed $mainCategory
 * @method getLang(string $string)
 * @method isSet()
 */
class ProductsInBasketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */

    public function toArray(Request $request): array
    {
        $categorySlugs = $this->whenLoaded('mainCategory', function () {
            $data = [
                'sub_category_slug' => $this->mainCategory?->slug,
            ];

            if ($this->mainCategory && $this->mainCategory->relationLoaded('parent') && $this->mainCategory->parent) {
                $data['parent_category_slug'] = $this->mainCategory->parent?->slug;
            }

            return $data;
        });

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'category_slugs' => $categorySlugs,
            'title' => $this->getLang('title'),
            'description_short' => $this->getLang('description_short'),
            'hover_image' => $this->whenLoaded('media', function () {
                return $this->hover_image;
            }),
            'is_set' => $this->isSet(),
            'brand' => BrandsListResource::make($this->whenLoaded('brand')),
        ];
    }
}
