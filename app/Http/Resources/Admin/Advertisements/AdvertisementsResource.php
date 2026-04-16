<?php

namespace App\Http\Resources\Admin\Advertisements;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $title
 * @property mixed $key
 * @property mixed $link
 * @property mixed $start_date
 * @property mixed $end_date
 * @property mixed $is_active
 * @property mixed $platform
 * @property mixed $click_count
 * @property mixed $creatable
 * @property mixed $created_at
 * @method isCurrentlyActive()
 * @method getFirstMediaUrl(string $collection)
 */
class AdvertisementsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'title'               => $this->title,
            'key'                 => $this->key,
            'link'                => $this->link,
            'image_url'           => $this->getFirstMediaUrl('advertisement_image') ?: null,
            'start_date'          => $this->start_date?->toDateString(),
            'end_date'            => $this->end_date?->toDateString(),
            'is_active'           => $this->is_active,
            'is_currently_active' => $this->isCurrentlyActive(),
            'platform'            => $this->platform,
            'click_count'         => $this->click_count,
            'created_user'        => $this->whenLoaded('creatable', fn() => $this->creatable?->username),
            'created_date'        => $this->created_at?->toDateTimeString(),
        ];
    }
}
