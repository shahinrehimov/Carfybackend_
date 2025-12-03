<?php

namespace App\Http\Resources\App\Groups;

use App\Http\Resources\App\ProductVariants\ProductVariantsResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $image
 * @property mixed $uuid
 * @property mixed $slug
 * @property mixed $name
 * @property mixed $products_data
 * @method getLang(string $string)
 */
class GroupsResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'title' => $this->getLang('title'),
            'description' => $this->getLang('description'),
            'image' => $this->whenLoaded('media', function () {
                return $this->image;
            }),
            'product_variants' => $this->when(!empty($this->products_data), function () {
                return ProductVariantsResource::collection($this->products_data);
            }),
        ];
    }
}
