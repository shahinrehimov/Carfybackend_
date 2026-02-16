<?php

namespace App\Http\Requests\Sellers\ProductVariants;

use App\Enum\DiscountTypeEnum;
use App\Models\BrandModel;
use App\Models\City;
use App\Models\ProductOption;
use App\Rules\FirstTranslationRequired;
use App\Rules\NestedDistinctRule;
use App\Rules\ProductOptionsRule;
use App\Services\LangService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * @property mixed $has_discount
 * @property mixed $is_set
 */
class ProductVariantStoreRequest extends FormRequest
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

            'city_id' => ['required', 'integer', Rule::exists(City::class, 'id')->where('is_active', true)->whereNull('deleted_at')],

            'brand_model_id' => ['required', 'integer',
                Rule::exists(BrandModel::class, 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->where('brand_id', $this->route('product')?->brand_id)
            ],

            'year' => ['required', 'integer', 'min:1900', 'max:' . date('Y')],

            'stock' => ['nullable', 'integer', 'min:0'],
            'price' => ['nullable', /*Rule::requiredIf(fn() => !$this->is_set),*/ Rule::excludeIf(fn() => $this->is_set), 'numeric', 'min:0'],
            'has_discount' => ['required', 'boolean'],
            'discount' => ['nullable', Rule::requiredIf(fn() => $this->has_discount), Rule::excludeIf(fn() => !$this->has_discount), 'numeric', 'min:0'],
            'discount_type' => ['nullable', Rule::requiredIf(fn() => $this->has_discount), Rule::excludeIf(fn() => !$this->has_discount), 'string', Rule::in(DiscountTypeEnum::cases())],
            'discount_start_at' => ['nullable', Rule::excludeIf(fn() => !$this->has_discount), 'date'],
            'discount_end_at' => ['nullable', Rule::excludeIf(fn() => !$this->has_discount), 'date', 'after_or_equal:discount_start_at'],

            'options' => ['filled', 'array', Rule::requiredIf(fn() => $this->checkRequiredOptions()), new ProductOptionsRule()],
            'options.*.option_id' => ['required', 'integer', Rule::exists(ProductOption::class, 'id')->where('is_active', true)->whereNull('deleted_at')],
            'options.*.value' => ['sometimes', 'string'],
            'options.*.translations' => ['sometimes', 'required', 'array', 'size:' . count(LangService::instance()->getLanguages())],
            'options.*.translations.*.language_id' => ['required', 'integer', new NestedDistinctRule(1), Rule::in(data_get(LangService::instance()->getLanguages(), '*.id'))],
            'options.*.translations.*.translatable_value' => [new FirstTranslationRequired('value'), 'string', 'max:10000'],
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
