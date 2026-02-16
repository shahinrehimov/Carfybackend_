<?php

namespace App\Http\Resources\Admin\Brands;

use App\Http\Resources\Admin\BrandModels\BrandModelsListResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $image
 * @property mixed $uuid
 * @property mixed $slug
 * @method getLang(string $string)
 */
class BrandsListResource extends JsonResource
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
            'image' => $this->whenLoaded('media', function () {
                return $this->image;
            }),
            'brand_models' => BrandModelsListResource::collection($this->whenLoaded('brandModels')),
        ];
    }
}
