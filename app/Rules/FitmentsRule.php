<?php

namespace App\Rules;

use App\Models\BrandModel;
use App\Services\LangService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Cross-field checks for a fitment list. Per-field existence and range checks stay
 * in the request rules; this covers what only makes sense across a whole row.
 */
readonly class FitmentsRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $modelIds = collect($value)->pluck('brand_model_id')->filter()->unique()->all();
        $brandByModel = BrandModel::query()->whereKey($modelIds)->pluck('brand_id', 'id');

        $seen = [];

        foreach ($value as $row) {
            $brandId = $row['brand_id'] ?? null;
            $modelId = $row['brand_model_id'] ?? null;
            $yearFrom = $row['year_from'] ?? null;
            $yearTo = $row['year_to'] ?? null;

            // brand_id is denormalised from the model so brand filters never join
            // brand_models; a model without its brand would break that assumption.
            if ($modelId !== null && $brandId === null) {
                $fail(
                    LangService::instance()
                        ->setDefault('The :attribute field must contain a brand whenever a model is given.')
                        ->getLang('fitments_validation_brand_required', ['@attribute' => $attribute])
                );

                return;
            }

            // The gap that let products.brand_id drift away from the variant's
            // models in the first place — now closed at the door.
            if ($modelId !== null && (int) ($brandByModel[$modelId] ?? 0) !== (int) $brandId) {
                $fail(
                    LangService::instance()
                        ->setDefault('The :attribute field contains a model that does not belong to the selected brand.')
                        ->getLang('fitments_validation_model_brand_mismatch', ['@attribute' => $attribute])
                );

                return;
            }

            if ($yearFrom !== null && $yearTo !== null && (int) $yearFrom > (int) $yearTo) {
                $fail(
                    LangService::instance()
                        ->setDefault('The :attribute field must not start after it ends.')
                        ->getLang('fitments_validation_year_order', ['@attribute' => $attribute])
                );

                return;
            }

            $signature = implode('|', [$brandId ?? '*', $modelId ?? '*', $yearFrom ?? '*', $yearTo ?? '*']);

            if (isset($seen[$signature])) {
                $fail(
                    LangService::instance()
                        ->setDefault('The :attribute field must not contain duplicate entries.')
                        ->getLang('fitments_validation_duplicate', ['@attribute' => $attribute])
                );

                return;
            }

            $seen[$signature] = true;
        }
    }
}
