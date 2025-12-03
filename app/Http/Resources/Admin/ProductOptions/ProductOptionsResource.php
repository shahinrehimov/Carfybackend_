<?php

namespace App\Http\Resources\Admin\ProductOptions;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $created_at
 * @property mixed $id
 * @property mixed $creatable
 * @property mixed $uuid
 * @property mixed $sort
 * @property mixed $name
 * @method getLang(string $string)
 * @method isActive()
 * @method isRequired()
 * @method hasItems()
 * @method isTranslatable()
 * @method canMultiple()
 */
class ProductOptionsResource extends JsonResource
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
            'title' => $this->getLang('title'),
            'is_required' => $this->isRequired(),
            'has_items' => $this->hasItems(),
            'items_count' => $this->whenCounted('items'),
            'is_translatable' => $this->isTranslatable(),
            'can_multiple' => $this->canMultiple(),
            'sort' => $this->sort ?? 0,
            'is_active' => $this->isActive(),
            'created_user' => $this->whenLoaded('creatable', function () {
                return $this->creatable?->username;
            }),
            'created_date' => $this->created_at?->toDateTimeString(),
        ];
    }
}
