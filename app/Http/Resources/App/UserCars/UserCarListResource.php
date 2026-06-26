<?php

namespace App\Http\Resources\App\UserCars;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight car representation for selectors (e.g. car-based product search).
 * Only fields shown in the UI are exposed; use UserCarResource for full data.
 */
class UserCarListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'photo' => $this->photo,
            'year'  => $this->year,
            'brand' => $this->whenLoaded('brandModel', function () {
                return [
                    'id'    => $this->brandModel->brand?->id,
                    'title' => $this->brandModel->brand?->getLang('title'),
                ];
            }),
            'brand_model' => $this->whenLoaded('brandModel', function () {
                return [
                    'id'    => $this->brandModel->id,
                    'title' => $this->brandModel->getLang('title'),
                ];
            }),
        ];
    }
}
