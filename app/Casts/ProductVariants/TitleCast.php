<?php

namespace App\Casts\ProductVariants;

use App\Models\ProductOption;
use App\Models\ProductVariant;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class TitleCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param ProductVariant|Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return string|null
     */
    public function get(ProductVariant|Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $model->loadMissing([
            'product',
            'product.translatable',
            'options',
            'options.option',
            'options.option.translatable',
            'options.item',
            'options.item.translatable',
        ]);

        $optionsTitles = [];

        foreach ($model->options as $optionItem) {
            /** @var ProductOption $option */
            $option = $optionItem->option;

            $value = $optionItem->value;
            $item = null;

            if ($option) {
                if ($option->hasItems()) {
                    $item = $optionItem->item;
                    $value = $item?->getLang('title');
                } else if ($option->isTranslatable()) {
                    $value = $optionItem->getLang('translatable_value');
                }
            }

            $optionsTitles[] = $value;
        }

        return $model->product->getLang('title') . (count($optionsTitles) ? (' ' . implode(' ', $optionsTitles)) : '');
    }

    /**
     * Prepare the given value for storage.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return bool
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): bool
    {
        return false;
    }
}
