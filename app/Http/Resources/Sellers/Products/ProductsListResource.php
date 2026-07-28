<?php

namespace App\Http\Resources\Sellers\Products;

use App\Services\FitmentPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $uuid
 * @property mixed $slug
 * @property mixed $main_image
 * @property mixed $pivot
 * @property mixed $hover_image
 * @property mixed $images
 * @method getLang(string $string)
 * @method isSet()
 */
class ProductsListResource extends JsonResource
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
            'title' => $this->getLang('title'),
            'main_image' => $this->whenLoaded('media', function () {
                return $this->main_image;
            }),
            'hover_image' => $this->whenLoaded('media', function () {
                return $this->hover_image;
            }),
            'images' => $this->whenLoaded('media', function () {
                return $this->images['urls'] ?? [];
            }),
            'is_set' => $this->isSet(),
            'quantity' => $this->whenLoaded('pivot', function () {
                return $this->pivot->quantity;
            }),
            // Brand moved onto the variants' fitment rules, so a product can now
            // span several. `brand` stays for existing clients and answers only
            // when the fitments agree on one.
            'brand' => FitmentPresenter::instance()->singleBrandForProduct($this->resource),
            'brands' => FitmentPresenter::instance()->brandsForProduct($this->resource),
            'is_universal' => FitmentPresenter::instance()->isUniversalProduct($this->resource),
        ];
    }
}
