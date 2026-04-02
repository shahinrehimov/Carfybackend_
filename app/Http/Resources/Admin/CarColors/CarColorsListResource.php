<?php

namespace App\Http\Resources\Admin\CarColors;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarColorsListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'title'    => $this->getLang('title'),
            'hex_code' => $this->hex_code,
        ];
    }
}
