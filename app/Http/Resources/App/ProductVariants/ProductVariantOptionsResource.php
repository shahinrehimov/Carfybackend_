<?php

namespace App\Http\Resources\App\ProductVariants;

use App\Http\Resources\Admin\ProductOptionItems\ProductOptionItemsListResource;
use App\Models\ProductOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $item
 * @property mixed $value
 * @method getLang(string $string)
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
        $value = $this->value;
        $item = null;

        /** @var ProductOption $option */
        $option = $this->whenLoaded('option');
        $optionLabel = '';
        $optionName = '';

        if ($option) {
            $optionLabel = $option->getLang('title');
            $optionName = $option->name;

            if ($option->hasItems()) {
                $item = $this->item;
                $value = $item?->getLang('title');
            } else if ($option->isTranslatable()) {
                $value = $this->getLang('translatable_value');
            }
        }

        return [
            'option_name' => $optionName,
            'option_label' => $optionLabel,
            'value' => $value,
            'item' => ProductOptionItemsListResource::make($this->whenNotNull($item, null)),
        ];
    }
}
