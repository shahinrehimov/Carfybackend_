<?php

namespace App\Http\Resources\Admin\CarTypes;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarTypesListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'title' => $this->getLang('title'),
        ];
    }
}
