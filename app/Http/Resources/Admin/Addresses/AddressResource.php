<?php

namespace App\Http\Resources\Admin\Addresses;

use App\Http\Resources\Admin\AddressTypes\AddressTypesListResource;
use App\Http\Resources\Admin\Cities\CitiesListResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $address_line_1
 * @property mixed $address_line_2
 * @property mixed $latitude
 * @property mixed $longitude
 * @property mixed $phone
 * @property mixed $id
 * @property mixed $created_at
 * @method isDefault()
 */
class AddressResource extends JsonResource
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
            'is_default' => $this->isDefault(),
            'address_type' => AddressTypesListResource::make($this->whenLoaded('addressType')),
            'city' => CitiesListResource::make($this->whenLoaded('city')),
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'phone' => $this->phone,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
