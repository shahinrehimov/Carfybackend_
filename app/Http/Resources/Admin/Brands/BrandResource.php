<?php

namespace App\Http\Resources\Admin\Brands;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $translations
 * @property mixed $image
 * @property mixed $uuid
 * @property mixed $sort
 * @property mixed $slug
 * @method isActive()
 */
class BrandResource extends JsonResource
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
            'image' => $this->whenLoaded('media', function () {
                return $this->image;
            }),
            'translations' => $this->translations,
            'sort' => $this->sort,
            'is_active' => $this->isActive(),
        ];
    }
}
