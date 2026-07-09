<?php

namespace App\Http\Requests\Admin\Faqs;

use App\Rules\FirstTranslationRequired;
use App\Services\LangService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FaqStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sort'                        => ['filled', 'integer', 'min:0'],
            'translations'                => ['required', 'array', 'size:' . count(LangService::instance()->getLanguages())],
            'translations.*.language_id'  => ['required', 'integer', 'distinct', Rule::in(data_get(LangService::instance()->getLanguages(), '*.id'))],
            'translations.*.question'     => [new FirstTranslationRequired('question'), 'string', 'max:1000'],
            'translations.*.answer'       => [new FirstTranslationRequired('answer'), 'string', 'max:10000'],
        ];
    }
}
