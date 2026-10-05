<?php

namespace App\Http\Resources\App\Products;

use App\Services\FitmentPresenter;
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
class ProductsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Every key is always present so that any list embedding a product exposes
     * the exact same structure. Category slugs in particular are required by the
     * clients to build the product detail URL, so they must never be omitted.
     *
     * @param Request $request
     * @return array
     */

    public function toArray(Request $request): array
    {
        $mainCategory = $this->relationLoaded('mainCategory') ? $this->mainCategory : null;
        $parentCategory = $mainCategory && $mainCategory->relationLoaded('parent') ? $mainCategory->parent : null;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'category_slugs' => [
                'parent_category_slug' => $parentCategory?->slug,
                'sub_category_slug' => $mainCategory?->slug,
            ],
            // Display names of the same categories. Titles are only read when
            // their translations were eager loaded, so a list never lazy-loads
            // them row by row.
            'category' => [
                'title' => $mainCategory?->relationLoaded('translatable') ? $mainCategory->getLang('title') : null,
                'parent_title' => $parentCategory?->relationLoaded('translatable') ? $parentCategory->getLang('title') : null,
            ],
            'title' => $this->getLang('title'),
            'description_short' => $this->getLang('description_short'),
            'hover_image' => $this->relationLoaded('media') ? $this->hover_image : null,
            'is_set' => $this->isSet(),
            // Brand moved onto the variants' fitment rules, so a product can now
            // span several. `brand` stays for existing clients and answers only
            // when the fitments agree on one.
            'brand' => FitmentPresenter::instance()->singleBrandForProduct($this->resource),
            'brands' => FitmentPresenter::instance()->brandsForProduct($this->resource),
            'is_universal' => FitmentPresenter::instance()->isUniversalProduct($this->resource),
        ];
    }
}
