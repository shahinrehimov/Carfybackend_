<?php

namespace App\Http\Resources\Admin\EngineVolumes;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EngineVolumeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'value_liters'  => $this->value_liters,
            'value_cc'      => $this->value_cc,
            'translations'  => $this->translations,
            'sort'          => $this->sort,
            'is_active'     => $this->isActive(),
        ];
    }
}
