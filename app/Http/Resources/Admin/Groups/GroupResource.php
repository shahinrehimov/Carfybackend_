<?php

namespace App\Http\Resources\Admin\Groups;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $translations
 * @property mixed $image
 * @property mixed $uuid
 * @property mixed $sort
 * @property mixed $slug
 * @property mixed $max_count
 * @property mixed $name
 * @method isActive()
 */
class GroupResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'max_count' => $this->max_count,
            'image' => $this->whenLoaded('media', function () {
                return $this->image;
            }),
            'translations' => $this->translations,
            'sort' => $this->sort,
            'is_active' => $this->isActive(),
        ];
    }
}
