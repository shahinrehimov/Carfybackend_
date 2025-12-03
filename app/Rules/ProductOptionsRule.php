<?php

namespace App\Rules;

use App\Models\ProductOption;
use App\Services\LangService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

readonly class ProductOptionsRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $requiredOptions = ProductOption::query()
            ->active()
            ->isRequired()
            ->pluck('id')
            ->toArray();

        $optionIds = collect($value)->pluck('option_id')->filter()->unique()->toArray();

        $missingOptions = array_diff($requiredOptions, $optionIds);

        if (!empty($missingOptions)) {
            $fail(
                LangService::instance()
                    ->setDefault('The :attribute field must contain all required product options.')
                    ->getLang('product_options_validation_required_options', [
                        '@attribute' => $attribute,
                    ])
            );

            return;
        }

        $selectedOptions = ProductOption::query()
            ->with([
                'items',
            ])
            ->whereIn('id', $optionIds)
            ->active()
            ->get();

        foreach ($value as $option) {
            if (empty($option['option_id'])) {
                $fail(
                    LangService::instance()
                        ->setDefault('The :attribute field must contain a valid product option ID.')
                        ->getLang('product_options_validation_option_id_required', [
                            '@attribute' => $attribute,
                        ])
                );

                return;
            }

            /* @var ProductOption $selectedOption */
            $selectedOption = $selectedOptions->firstWhere('id', $option['option_id']);

            if (!$selectedOption) {
                $fail(
                    LangService::instance()
                        ->setDefault('The :attribute field contains an invalid product option.')
                        ->getLang('product_options_validation_invalid_option', [
                            '@attribute' => $attribute,
                        ])
                );

                return;
            }

            if ($selectedOption->isTranslatable() && empty($option['translations'])) {
                $fail(
                    LangService::instance()
                        ->setDefault('The :attribute field must contain translations for translatable product options.')
                        ->getLang('product_options_validation_translations_required', [
                            '@attribute' => $attribute,
                        ])
                );

                return;
            }

            if (!$selectedOption->isTranslatable() && empty($option['value'])) {
                $fail(
                    LangService::instance()
                        ->setDefault('The :attribute field must contain a value for non-translatable product options.')
                        ->getLang('product_options_validation_value_required', [
                            '@attribute' => $attribute,
                        ])
                );

                return;
            }

            if ($selectedOption->hasItems() && (!is_numeric($option['value']) || !in_array((int) $option['value'], $selectedOption->items->pluck('id')->toArray(), true))) {
                $fail(
                    LangService::instance()
                        ->setDefault('The :attribute field contains an invalid product option item.')
                        ->getLang('product_options_validation_invalid_item', [
                            '@attribute' => $attribute,
                        ])
                );

                return;
            }
        }
    }
}
