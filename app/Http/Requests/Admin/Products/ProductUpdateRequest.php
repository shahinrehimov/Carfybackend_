<?php

namespace App\Http\Requests\Admin\Products;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\LangService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * @property mixed $has_discount
 * @property mixed $is_set
 */
class ProductUpdateRequest extends FormRequest
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
        /** @var Product $product */
        $product = $this->route('product');

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

            'slug' => ['nullable', 'string', 'max:10000'],

            'sets' => ['filled', Rule::requiredIf(fn() => $product->isSet()), Rule::excludeIf(fn() => !$product->isSet()), 'array', 'min:1'],
            'sets.*.product_variant_id' => ['required', 'integer', Rule::exists(ProductVariant::class, 'id')->where('is_active', true)->where('is_set', 0)->whereNull('deleted_at')],
            'sets.*.quantity' => ['required', 'integer', 'min:1'],

            'brand_id' => ['required', 'integer', Rule::exists(Brand::class, 'id')->where('is_active', true)->whereNull('deleted_at')],
            'main_category_id' => ['required', 'integer', Rule::in($this->categories ?? []), Rule::exists(Category::class, 'id')->where('is_active', true)->whereNull('deleted_at')],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['required', 'integer', Rule::exists(Category::class, 'id')->where('is_active', true)->whereNull('deleted_at')],

            'translations' => ['required', 'array', 'size:' . count(LangService::instance()->getLanguages())],
            'translations.*.language_id' => ['required', 'integer', 'distinct', Rule::in(data_get(LangService::instance()->getLanguages(), '*.id'))],
            'translations.*.title' => ['required', 'string', 'max:1000'],
            'translations.*.description_short' => ['required', 'string', 'max:10000'],
            'translations.*.description' => ['nullable', 'string', 'max:50000'],
            'translations.*.delivery' => ['nullable', 'string', 'max:50000'],
            'translations.*.note' => ['nullable', 'string', 'max:50000'],
        ];
    }
}
