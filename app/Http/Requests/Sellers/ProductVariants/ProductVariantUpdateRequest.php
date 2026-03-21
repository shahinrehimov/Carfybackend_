<?php

namespace App\Http\Requests\Sellers\ProductVariants;

use App\Models\BrandModel;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use App\Rules\NestedDistinctRule;
use App\Rules\ProductOptionsRule;
use App\Services\LangService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * @property mixed $is_set
 */
class ProductVariantUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        /** @var ProductVariant $productVariant */
        $productVariant = $this->route('productVariant');

        return [
            'main_image' => [
                'filled',
                File::image()->max(10 * 1024),
            ],
            'hover_image' => [
                'filled',
                File::image()->max(10 * 1024),
            ],
            'images' => ['filled', 'array', 'min:1'],
            'images.*' => ['required', File::image()->max(10 * 1024)],

            'brand_model_ids' => ['required', 'array', 'min:1'],
            'brand_model_ids.*' => ['required', 'integer',
                Rule::exists(BrandModel::class, 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
            ],

            'oem_code' => ['nullable', 'string', 'max:100'],

            'year' => ['required', 'integer', 'min:1900', 'max:' . date('Y')],

            'sets' => ['filled', Rule::requiredIf(fn() => $productVariant->isSet()), Rule::excludeIf(fn() => !$productVariant->isSet()), 'array', 'min:1'],
            'sets.*.product_variant_id' => ['required', 'integer', Rule::exists(ProductVariant::class, 'id')->where('is_active', true)->where('is_set', 0)->whereNull('deleted_at')],
            'sets.*.quantity' => ['required', 'integer', 'min:1'],

            'options' => ['filled', 'array', Rule::requiredIf(fn() => $this->checkRequiredOptions()), new ProductOptionsRule()],
            'options.*.option_id' => ['required', 'integer', Rule::exists(ProductOption::class, 'id')->where('is_active', true)->whereNull('deleted_at')],
            'options.*.value' => ['sometimes', 'string'],
            'options.*.translations' => ['sometimes', 'required', 'array', 'size:' . count(LangService::instance()->getLanguages())],
            'options.*.translations.*.language_id' => ['required', 'integer', new NestedDistinctRule(1), Rule::in(data_get(LangService::instance()->getLanguages(), '*.id'))],
            'options.*.translations.*.translatable_value' => ['required', 'string', 'max:10000'],
        ];
    }

    private function checkRequiredOptions(): bool
    {
        $requestOptions = $this->options ?? [];

        $requiredOptions = ProductOption::query()
            ->active()
            ->isRequired()
            ->pluck('id')
            ->toArray();

        $optionIds = collect($requestOptions)->pluck('option_id')->filter()->unique()->toArray();

        $missingOptions = array_diff($requiredOptions, $optionIds);

        return !empty($missingOptions);
    }
}
