<?php

namespace App\Http\Resources\Admin\Pages;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $sort
 * @property mixed $translations
 * @property mixed $main_image
 * @property mixed $icon
 * @property mixed $images
 * @method isActive()
 */
class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'main_image' => $this->whenLoaded('media', function () {
                return $this->main_image;
            }),
            'icon' => $this->whenLoaded('media', function () {
                return $this->icon;
            }),
            'images' => $this->whenLoaded('media', function () {
                return $this->images;
            }),
            'translations' => $this->translations,
            'sort' => $this->sort,
            'is_active' => $this->isActive(),
        ];
    }
}
