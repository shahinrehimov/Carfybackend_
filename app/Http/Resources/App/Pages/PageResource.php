<?php

namespace App\Http\Resources\App\Pages;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $main_image
 * @property mixed $icon
 * @property mixed $images
 * @method getLang(string $string)
 */
class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->getLang('title'),
            'description' => $this->getLang('description'),
            'body' => $this->getLang('body'),
            'main_image' => $this->whenLoaded('media', function () {
                return $this->main_image;
            }),
            'icon' => $this->whenLoaded('media', function () {
                return $this->icon;
            }),
            'images' => $this->whenLoaded('media', function () {
                return $this->images['urls'] ?? [];
            }),
            'meta_title' => $this->getLang('meta_title'),
            'meta_description' => $this->getLang('meta_description'),
            'meta_keywords' => $this->getLang('meta_keywords'),
        ];
    }
}
