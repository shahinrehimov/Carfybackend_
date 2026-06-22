<?php

namespace App\Http\Resources\Sellers\ProductVariants;

use App\Http\Resources\Admin\ProductOptions\ProductOptionsListResource;
use App\Models\ProductOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $value
 * @property mixed $product_option_id
 * @property mixed $product_option_item_id
 * @property mixed $translations
 */
class ProductVariantOptionsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */

    public function toArray(Request $request): array
    {
        /** @var ProductOption|null $option */
        $option = $this->relationLoaded('option') ? $this->getRelation('option') : null;

        $value = $this->value;

        if ($option && $option->hasItems()) {
            $value = (int) $this->product_option_item_id;
        }

        $translations = [];
        if ($option && $option->isTranslatable()) {
            $translations = $this->translations;
            $value = null;
        }

        return [
            'id' => $this->id,
            'option_id' => $this->product_option_id,
            'value' => $value,
            'translations' => $translations,
            'option' => $option ? ProductOptionsListResource::make($option) : null,
        ];
    }
}
