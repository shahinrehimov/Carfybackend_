<?php

namespace App\Http\Resources\Admin\Groups;

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
 * @property mixed $name
 * @property mixed $max_count
 * @method getLang(string $string)
 * @method isActive()
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
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'title' => $this->getLang('title'),
            'max_count' => $this->max_count,
            'image' => $this->whenLoaded('media', function () {
                return $this->image;
            }),
            'products_count' => $this->whenCounted('productVariants'),
            'sort' => $this->sort,
            'is_active' => $this->isActive(),
            'created_user' => $this->whenLoaded('creatable', function () {
                return $this->creatable?->username;
            }),
            'created_date' => $this->created_at?->toDateTimeString(),
        ];
    }
}
