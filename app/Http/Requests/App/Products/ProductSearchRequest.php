<?php

namespace App\Http\Requests\App\Products;

use App\Enum\ProductSearchStatusEnum;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductSearchRequest extends FormRequest
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
            'sort' => ['filled', 'string', 'in:date,title,price'],
            'sort_type' => ['filled', 'string', 'in:asc,desc'],
            'limit' => ['filled', 'integer', 'min:5', 'max:100'],
            'search' => ['nullable', 'string', 'max:500'],

            'is_set' => ['nullable', 'string', 'in:yes,no'],
            'brands' => ['nullable', 'array'],
            'brands.*' => ['required', 'string', 'max:10000', Rule::exists(Brand::class, 'slug')->where('is_active', true)->whereNull('deleted_at')],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['required', 'string', 'max:10000', Rule::exists(Category::class, 'slug')->where('is_active', true)->whereNull('parent_id')->whereNull('deleted_at')],
            'sub_categories' => ['nullable', 'array'],
            'sub_categories.*' => ['required', 'string', 'max:10000', Rule::exists(Category::class, 'slug')->where('is_active', true)->whereNotNull('parent_id')->whereNull('deleted_at')],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['nullable', 'string', Rule::in(ProductSearchStatusEnum::cases())],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric'],
        ];
    }
}
