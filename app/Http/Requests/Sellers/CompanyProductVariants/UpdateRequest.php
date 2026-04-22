<?php

namespace App\Http\Requests\Sellers\CompanyProductVariants;

use App\Enum\DiscountTypeEnum;
use App\Enum\ProductSizeCategoryEnum;
use App\Models\City;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property mixed $has_discount
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stock' => ['nullable', 'integer', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'has_discount' => ['required', 'boolean'],
            'discount' => ['nullable', Rule::requiredIf(fn() => $this->has_discount), Rule::excludeIf(fn() => !$this->has_discount), 'numeric', 'min:0'],
            'discount_type' => ['nullable', Rule::requiredIf(fn() => $this->has_discount), Rule::excludeIf(fn() => !$this->has_discount), 'string', Rule::in(DiscountTypeEnum::cases())],
            'discount_start_at' => ['nullable', Rule::excludeIf(fn() => !$this->has_discount), 'date'],
            'discount_end_at' => ['nullable', Rule::excludeIf(fn() => !$this->has_discount), 'date', 'after_or_equal:discount_start_at'],
            'city_id' => ['nullable', 'integer', Rule::exists(City::class, 'id')->where('is_active', true)->whereNull('deleted_at')],
            'size_category' => ['required', 'string', Rule::in(ProductSizeCategoryEnum::cases())],
            'weight_gram' => ['required', 'integer', 'min:1', 'max:20000'],
        ];
    }
}
