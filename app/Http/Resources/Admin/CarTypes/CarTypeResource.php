<?php

namespace App\Http\Resources\Admin\CarTypes;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'translations' => $this->translations,
            'sort'         => $this->sort,
            'is_active'    => $this->isActive(),
        ];
    }
}
