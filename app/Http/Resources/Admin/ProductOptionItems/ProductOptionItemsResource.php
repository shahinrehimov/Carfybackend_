<?php

namespace App\Http\Resources\Admin\ProductOptionItems;

use App\Http\Resources\Admin\ProductOptions\ProductOptionsListResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $created_at
 * @property mixed $id
 * @property mixed $creatable
 * @property mixed $uuid
 * @property mixed $sort
 * @method getLang(string $string)
 * @method isActive()
 */
class ProductOptionItemsResource extends JsonResource
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
            'title' => $this->getLang('title'),
            'product_option' => ProductOptionsListResource::make($this->whenLoaded('productOption')),
            'sort' => $this->sort ?? 0,
            'is_active' => $this->isActive(),
            'created_user' => $this->whenLoaded('creatable', function () {
                return $this->creatable?->username;
            }),
            'created_date' => $this->created_at?->toDateTimeString(),
        ];
    }
}
