<?php

namespace App\Http\Resources\Admin\Sliders;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $type
 * @property mixed $slug
 * @property mixed $sort
 * @method getLang(string $string)
 * @method isActive()
 */
class SlidersListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->getLang('title'),
            'slug' => $this->slug,
            'type' => $this->type,
            'is_active' => $this->isActive(),
            'sort' => $this->sort,
        ];
    }
}
