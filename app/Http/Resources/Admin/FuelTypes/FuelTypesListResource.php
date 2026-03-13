<?php

namespace App\Http\Resources\Admin\FuelTypes;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FuelTypesListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'title' => $this->getLang('title'),
        ];
    }
}
