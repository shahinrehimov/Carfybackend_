<?php

namespace App\Http\Requests\Admin\Groups;

use App\Rules\FirstTranslationRequired;
use App\Services\LangService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class GroupStoreRequest extends FormRequest
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
            'max_count' => ['nullable', 'integer', 'min:1'],
            'sort' => ['filled', 'integer', 'min:0'],
            'slug' => ['nullable', 'string', 'max:10000'],
            'translations' => ['required', 'array', 'size:' . count(LangService::instance()->getLanguages())],
            'translations.*.language_id' => ['required', 'integer', 'distinct', Rule::in(data_get(LangService::instance()->getLanguages(), '*.id'))],
            'translations.*.title' => [new FirstTranslationRequired('title'), 'string', 'max:5000'],
            'translations.*.description' => ['nullable', 'string', 'max:50000'],
        ];
    }
}
