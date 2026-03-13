<?php

namespace App\Http\Resources\Admin\CarTypes;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarTypesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->getLang('title'),
            'sort'         => $this->sort,
            'is_active'    => $this->isActive(),
            'created_user' => $this->whenLoaded('creatable', fn() => $this->creatable?->username),
            'created_date' => $this->created_at?->toDateTimeString(),
        ];
    }
}
