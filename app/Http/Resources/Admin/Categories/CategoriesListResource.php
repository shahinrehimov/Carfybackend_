<?php

namespace App\Http\Resources\Admin\Categories;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $image
 * @property mixed $uuid
 * @property mixed $slug
 * @method getLang(string $string)
 */
class CategoriesListResource extends JsonResource
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
            'parent' => CategoriesListResource::make($this->whenLoaded('parent')),
            'subs' => CategoriesListResource::collection($this->whenLoaded('subs')),
        ];
    }
}
