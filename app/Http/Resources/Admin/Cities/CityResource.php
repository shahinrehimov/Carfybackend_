<?php

namespace App\Http\Resources\Admin\Cities;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $translations
 * @property mixed $sort
 * @method isActive()
 */
class CityResource extends JsonResource
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
            'translations' => $this->translations,
            'sort' => $this->sort,
            'is_active' => $this->isActive(),
        ];
    }
}
