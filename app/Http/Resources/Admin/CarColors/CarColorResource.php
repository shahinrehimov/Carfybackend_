<?php

namespace App\Http\Resources\Admin\CarColors;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarColorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'translations' => $this->translations,
            'hex_code'     => $this->hex_code,
            'sort'         => $this->sort,
            'is_active'    => $this->isActive(),
        ];
    }
}
