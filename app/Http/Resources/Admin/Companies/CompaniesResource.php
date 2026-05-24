<?php

namespace App\Http\Resources\Admin\Companies;

use App\Http\Resources\Admin\Cities\CitiesListResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $uuid
 * @property mixed $voen
 * @property mixed $phone
 * @property mixed $email
 * @property mixed $verified_at
 * @property mixed $created_at
 * @property mixed $creatable
 * @property mixed $logo
 * @property mixed $store_code
 * @method getLang(string $string)
 * @method isActive()
 */
class CompaniesResource extends JsonResource
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
            'voen' => $this->voen,
            'phone' => $this->phone,
            'email' => $this->email,
            'title' => $this->getLang('title'),
            'store_code' => $this->store_code,
            'logo' => $this->whenLoaded('media', function () {
                return $this->logo;
            }),
            'city' => CitiesListResource::make($this->whenLoaded('city')),
            'is_active' => $this->isActive(),
            'is_verified' => !is_null($this->verified_at),
            'created_user' => $this->whenLoaded('creatable', function () {
                return $this->creatable?->username;
            }),
            'created_date' => $this->created_at?->toDateTimeString(),
        ];
    }
}
