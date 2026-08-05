<?php

namespace App\Http\Requests\Admin\Categories;

use App\Models\Category;
use App\Rules\CommissionTiersRule;
use App\Rules\FirstTranslationRequired;
use App\Services\LangService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class CategoryStoreRequest extends FormRequest
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
            'image' => [
                'filled',
                File::image()->max(10 * 1024),
            ],
            'slug' => ['nullable', 'string', 'max:10000'],
            'parent_id' => ['nullable', 'integer', Rule::exists(Category::class, 'id')->whereNull('parent_id')->where('is_active', true)->whereNull('deleted_at')],
            'sort' => ['filled', 'integer', 'min:0'],
            // null = inherit the platform default commission rate
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // Tiered override, amounts in manats: the order line amount picks
            // the percent. Empty array / null = drop the tiers.
            'commission_tiers' => ['sometimes', 'nullable', 'array', new CommissionTiersRule()],
            'commission_tiers.*.min_amount' => ['required', 'numeric', 'min:0'],
            'commission_tiers.*.max_amount' => ['nullable', 'numeric', 'min:0'],
            'commission_tiers.*.percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'translations' => ['required', 'array', 'size:' . count(LangService::instance()->getLanguages())],
            'translations.*.language_id' => ['required', 'integer', 'distinct', Rule::in(data_get(LangService::instance()->getLanguages(), '*.id'))],
            'translations.*.title' => [new FirstTranslationRequired('title'), 'string', 'max:500'],
        ];
    }
}
