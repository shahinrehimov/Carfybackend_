<?php

namespace App\Http\Resources\Admin\Groups;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $image
 * @property mixed $uuid
 * @property mixed $slug
 * @property mixed $name
 * @method getLang(string $string)
 */
class GroupsListResource extends JsonResource
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
            'image' => $this->whenLoaded('media', function () {
                return $this->image;
            }),
        ];
    }
}
