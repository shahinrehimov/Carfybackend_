<?php

namespace App\Http\Resources\Admin\Categories;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $translations
 * @property mixed $image
 * @property mixed $uuid
 * @property mixed $sort
 * @property mixed $parent_id
 * @property mixed $slug
 * @property mixed $commission_percent
 * @method isActive()
 */
class CategoryResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'translations' => $this->translations,
            'sort' => $this->sort,
            'is_active' => $this->isActive(),
            // null = inherits the platform default
            'commission_percent' => $this->commission_percent,
        ];
    }
}
