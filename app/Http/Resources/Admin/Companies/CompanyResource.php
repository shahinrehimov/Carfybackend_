<?php

namespace App\Http\Resources\Admin\Companies;

use App\Http\Resources\Admin\Cities\CitiesListResource;
use App\Http\Resources\Sellers\StoreProfile\StoreProfileWorkingHourResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $uuid
 * @property mixed $voen
 * @property mixed $phone
 * @property mixed $email
 * @property mixed $latitude
 * @property mixed $longitude
 * @property mixed $website
 * @property mixed $root_admin_email
 * @property mixed $verified_at
 * @property mixed $translations
 * @property mixed $logo
 * @property mixed $banner
 * @property mixed $city_id
 * @property mixed $avg_rating
 * @property mixed $review_count
 * @method isActive()
 */
class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'voen' => $this->voen,
            'phone' => $this->phone,
            'email' => $this->email,
            'city_id' => $this->city_id,
            'city' => CitiesListResource::make($this->whenLoaded('city')),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'website' => $this->website,
            'root_admin_email' => $this->root_admin_email,
            'verified_at' => $this->verified_at,
            'is_active' => $this->isActive(),
            'logo' => $this->whenLoaded('media', function () {
                return $this->logo;
            }),
            'banner' => $this->whenLoaded('media', function () {
                return $this->banner;
            }),
            'translations' => $this->translations,
            'working_hours' => StoreProfileWorkingHourResource::collection(
                $this->whenLoaded('workingHours')
            ),
            'avg_rating'   => $this->avg_rating ?? null,
            'review_count' => $this->review_count ?? 0,
        ];
    }
}
