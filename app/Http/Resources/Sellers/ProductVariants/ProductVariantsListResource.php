<?php

namespace App\Http\Resources\Sellers\ProductVariants;

use App\Http\Resources\Sellers\Products\ProductsListResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $uuid
 * @property mixed $slug
 * @property mixed $main_image
 * @property mixed $pivot
 * @property mixed $oem_code
 * @property mixed $stock
 * @property mixed $price
 * @property mixed $price_final
 * @method isSet()
 * @method isDefault()
 */
class ProductVariantsListResource extends JsonResource
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
            'product' => ProductsListResource::make($this->whenLoaded('product')),
            'main_image' => $this->whenLoaded('media', function () {
                return $this->main_image;
            }),
            'is_set' => $this->isSet(),
            'quantity' => $this->whenLoaded('pivot', function () {
                return $this->pivot->quantity;
            }),
            'stock' => $this->whenLoaded(
                'companyListings',
                fn() => $this->companyListings->first()?->stock,
                $this->stock
            ),
            'price' => $this->whenLoaded(
                'companyListings',
                fn() => $this->companyListings->first()?->price,
                $this->price
            ),
            'price_final' => $this->whenLoaded(
                'companyListings',
                fn() => $this->companyListings->first()?->price_final,
                $this->price_final
            ),
            'is_default' => $this->isDefault(),
            'sets' => ProductVariantsListResource::collection($this->whenLoaded('sets')),
        ];
    }
}
