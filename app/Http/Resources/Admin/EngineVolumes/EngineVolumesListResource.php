<?php

namespace App\Http\Resources\Admin\EngineVolumes;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EngineVolumesListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->getLang('title'),
            'value_liters' => $this->value_liters,
            'value_cc'     => $this->value_cc,
        ];
    }
}
