<?php

namespace App\Http\Resources\Admin\Pages;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $sort
 * @property mixed $created_at
 * @property mixed $creatable
 * @method getLang(string $string)
 * @method isActive()
 */
class PagesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->getLang('title'),
            'main_image' => $this->whenLoaded('media', function () {
                return $this->main_image;
            }),
            'sort' => $this->sort,
            'is_active' => $this->isActive(),
            'created_user' => $this->whenLoaded('creatable', function () {
                return $this->creatable?->username;
            }),
            'created_date' => $this->created_at?->toDateTimeString(),
        ];
    }
}
