<?php

namespace App\Http\Requests\Admin\CarColors;

use App\Rules\FirstTranslationRequired;
use App\Services\LangService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CarColorStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hex_code'                    => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort'                        => ['filled', 'integer', 'min:0'],
            'translations'                => ['required', 'array', 'size:' . count(LangService::instance()->getLanguages())],
            'translations.*.language_id'  => ['required', 'integer', 'distinct', Rule::in(data_get(LangService::instance()->getLanguages(), '*.id'))],
            'translations.*.title'        => [new FirstTranslationRequired('title'), 'string', 'max:300'],
        ];
    }
}
