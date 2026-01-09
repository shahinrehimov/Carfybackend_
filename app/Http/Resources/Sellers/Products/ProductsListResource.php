<?php

namespace App\Http\Resources\Sellers\Products;

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
        ];
    }
}
