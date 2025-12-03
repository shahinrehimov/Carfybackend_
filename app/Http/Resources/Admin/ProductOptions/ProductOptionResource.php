<?php

namespace App\Http\Resources\Admin\ProductOptions;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $translations
 * @property mixed $uuid
 * @property mixed $sort
 * @property mixed $name
 * @method isActive()
 * @method isRequired()
 * @method hasItems()
 * @method isTranslatable()
 * @method canMultiple()
 */
class ProductOptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'is_required' => $this->isRequired(),
            'has_items' => $this->hasItems(),
            'is_translatable' => $this->isTranslatable(),
            'can_multiple' => $this->canMultiple(),
            'translations' => $this->translations,
            'sort' => $this->sort,
            'is_active' => $this->isActive(),
        ];
    }
}
