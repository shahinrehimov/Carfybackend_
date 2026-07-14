<?php

namespace App\Http\Resources\App\Advertisements;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $title
 * @property mixed $key
 * @property mixed $link
 * @method getFirstMediaUrl(string $collection)
 */
class AdvertisementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'title'     => $this->title,
            'key'       => $this->key,
            'image_url' => $this->getWebImageUrl('advertisement_image'),
            'link'      => route('advertisements.click', ['key' => $this->key]),
        ];
    }
}
