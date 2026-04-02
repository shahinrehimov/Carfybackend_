<?php

namespace App\Http\Resources\App\Pages;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $main_image
 * @property mixed $icon
 * @method getLang(string $string)
 */
class PagesListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->getLang('title'),
            'description' => $this->getLang('description'),
            'main_image' => $this->whenLoaded('media', function () {
                return $this->main_image;
            }),
            'icon' => $this->whenLoaded('media', function () {
                return $this->icon;
            }),
        ];
    }
}
