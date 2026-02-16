<?php

namespace App\Http\Resources\Admin\BrandModels;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @method getLang(string $string)
 */
class BrandModelsListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->getLang('title'),
        ];
    }
}
