<?php

namespace App\Http\Resources\Admin\Categories;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $created_at
 * @property mixed $id
 * @property mixed $creatable
 * @property mixed $image
 * @property mixed $uuid
 * @property mixed $sort
 * @property mixed $slug
 * @method getLang(string $string)
 * @method isActive()
 */
class CategoriesResource extends JsonResource
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
            'subs_count' => $this->whenCounted('subs'),
            'parent' => CategoriesListResource::make($this->whenLoaded('parent')),
            'subs' => CategoriesListResource::collection($this->whenLoaded('subs')),
            'sort' => $this->sort ?? 0,
            'is_active' => $this->isActive(),
            'created_user' => $this->whenLoaded('creatable', function () {
                return $this->creatable?->username;
            }),
            'created_date' => $this->created_at?->toDateTimeString(),
        ];
    }
}
