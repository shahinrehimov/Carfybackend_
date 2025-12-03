<?php

namespace App\Http\Resources\Admin\ProductOptions;

use App\Http\Resources\Admin\ProductOptionItems\ProductOptionItemsListResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $uuid
 * @property mixed $name
 * @method getLang(string $string)
 * @method isRequired()
 * @method hasItems()
 * @method isTranslatable()
 * @method canMultiple()
 */
class ProductOptionsListResource extends JsonResource
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
            'is_translatable' => $this->isTranslatable(),
            'can_multiple' => $this->canMultiple(),
            'items' => ProductOptionItemsListResource::collection($this->whenLoaded('items')),
        ];
    }
}
