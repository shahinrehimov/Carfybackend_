<?php

namespace App\Http\Resources\Admin\Cities;

use App\Http\Resources\Admin\Countries\CountriesListResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $country_id
 * @method getLang(string $string)
 */
class CitiesListResource extends JsonResource
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
            'title' => $this->getLang('title'),
            'country_id' => $this->country_id,
            'country' => CountriesListResource::make($this->whenLoaded('country')),
        ];
    }
}
