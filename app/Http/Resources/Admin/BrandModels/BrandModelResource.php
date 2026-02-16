<?php

namespace App\Http\Resources\Admin\BrandModels;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $translations
 * @property mixed $sort
 * @method isActive()
 */
class BrandModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'translations' => $this->translations,
            'sort' => $this->sort,
            'is_active' => $this->isActive(),
        ];
    }
}
