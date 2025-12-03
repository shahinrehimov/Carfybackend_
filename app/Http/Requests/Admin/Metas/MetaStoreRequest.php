<?php

namespace App\Http\Requests\Admin\Metas;

use App\Models\Meta;
use App\Rules\FirstTranslationRequired;
use App\Services\LangService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MetaStoreRequest extends FormRequest
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
            'page_title' => ['required', 'string', 'max:100'],
            'page_key' => ['required', 'string', 'max:50', Rule::unique(Meta::class, 'page_key')->whereNull('deleted_at')],
            'translations' => ['required', 'array', 'size:' . count(LangService::instance()->getLanguages())],
            'translations.*.language_id' => ['required', 'integer', 'distinct', Rule::in(data_get(LangService::instance()->getLanguages(), '*.id'))],
            'translations.*.title' => [new FirstTranslationRequired('title'), 'max:1000'],
            'translations.*.keywords' => [new FirstTranslationRequired('keywords'), 'max:10000'],
            'translations.*.description' => [new FirstTranslationRequired('description'), 'max:10000'],
        ];
    }
}
