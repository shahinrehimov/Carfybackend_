<?php

namespace App\Http\Requests\Concerns;

use App\Models\Brand;
use App\Models\BrandModel;
use App\Rules\FitmentsRule;
use Illuminate\Validation\Rule;

/**
 * Shared fitment rules for the three seller endpoints that accept them.
 *
 * A fitment row means "fits this car", where a missing value means "all": no brand
 * fits every car, a brand without a model fits all of its models, and an open year
 * bound is unrestricted on that side.
 */
trait ValidatesFitments
{
    protected function fitmentRules(): array
    {
        // Next year's models reach the market before the calendar catches up.
        $maxYear = (int) date('Y') + 1;

        return [
            // Shorthand for a single all-null row, so clients selling universal
            // goods do not have to hand-build one.
            'is_universal' => ['nullable', 'boolean'],

            'fitments' => [
                Rule::excludeIf(fn () => $this->boolean('is_universal')),
                'required',
                'array',
                'min:1',
                new FitmentsRule(),
            ],
            'fitments.*.brand_id' => [
                'nullable',
                'integer',
                Rule::exists(Brand::class, 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'fitments.*.brand_model_id' => [
                'nullable',
                'integer',
                Rule::exists(BrandModel::class, 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'fitments.*.year_from' => ['nullable', 'integer', 'min:1900', 'max:' . $maxYear],
            'fitments.*.year_to' => ['nullable', 'integer', 'min:1900', 'max:' . $maxYear],
        ];
    }
}
