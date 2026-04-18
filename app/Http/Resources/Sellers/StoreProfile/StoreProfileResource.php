<?php

namespace App\Http\Resources\Sellers\StoreProfile;

use App\Http\Resources\Admin\Cities\CitiesListResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $uuid
 * @property mixed $store_code
 * @property mixed $voen
 * @property mixed $phone
 * @property mixed $email
 * @property mixed $website
 * @property mixed $city_id
 * @property mixed $latitude
 * @property mixed $longitude
 * @property mixed $verified_at
 * @property mixed $logo
 * @property mixed $banner
 * @property mixed $translations
 * @property mixed $avg_rating
 * @property mixed $review_count
 * @method isActive()
 */
class StoreProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'uuid'         => $this->uuid,
            'store_code'   => $this->store_code,
            'voen'         => $this->voen,
            'phone'        => $this->phone,
            'email'        => $this->email,
            'website'      => $this->website,
            'city_id'      => $this->city_id,
            'city'         => CitiesListResource::make($this->whenLoaded('city')),
            'latitude'     => $this->latitude,
            'longitude'    => $this->longitude,
            'verified_at'  => $this->verified_at?->toDateTimeString(),
            'is_active'    => $this->isActive(),
            'logo'         => $this->whenLoaded('media', fn() => $this->logo),
            'banner'       => $this->whenLoaded('media', fn() => $this->banner),
            'translations' => $this->translations,
            'working_hours' => StoreProfileWorkingHourResource::collection(
                $this->whenLoaded('workingHours')
            ),
            'avg_rating'   => $this->avg_rating ?? null,
            'review_count' => $this->review_count ?? 0,
        ];
    }
}
