<?php

namespace App\Http\Requests\Admin\Categories;

use App\Models\Category;
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
            'translations' => ['required', 'array', 'size:' . count(LangService::instance()->getLanguages())],
            'translations.*.language_id' => ['required', 'integer', 'distinct', Rule::in(data_get(LangService::instance()->getLanguages(), '*.id'))],
            'translations.*.title' => [new FirstTranslationRequired('title'), 'string', 'max:500'],
        ];
    }
}
