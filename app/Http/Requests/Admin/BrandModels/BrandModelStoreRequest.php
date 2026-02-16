<?php

namespace App\Http\Requests\Admin\BrandModels;

use App\Rules\FirstTranslationRequired;
use App\Services\LangService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandModelStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sort' => ['filled', 'integer', 'min:0'],
            'translations' => ['required', 'array', 'size:' . count(LangService::instance()->getLanguages())],
            'translations.*.language_id' => ['required', 'integer', 'distinct', Rule::in(data_get(LangService::instance()->getLanguages(), '*.id'))],
            'translations.*.title' => [new FirstTranslationRequired('title'), 'string', 'max:500'],
        ];
    }
}
