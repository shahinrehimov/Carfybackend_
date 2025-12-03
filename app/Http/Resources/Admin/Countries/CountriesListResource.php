<?php

namespace App\Http\Resources\Admin\Countries;

use App\Http\Resources\Admin\Cities\CitiesListResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @method getLang(string $string)
 */
class CountriesListResource extends JsonResource
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
            'cities' => CitiesListResource::collection($this->whenLoaded('cities')),
        ];
    }
}
