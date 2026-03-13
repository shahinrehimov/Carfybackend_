<?php

namespace App\Http\Requests\Admin\EngineVolumes;

use App\Services\LangService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EngineVolumeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'value_liters'                => ['required', 'numeric', 'min:0.1', 'max:99.9'],
            'value_cc'                    => ['required', 'integer', 'min:1'],
            'sort'                        => ['filled', 'integer', 'min:0'],
            'translations'                => ['required', 'array', 'size:' . count(LangService::instance()->getLanguages())],
            'translations.*.language_id'  => ['required', 'integer', 'distinct', Rule::in(data_get(LangService::instance()->getLanguages(), '*.id'))],
            'translations.*.title'        => ['required', 'string', 'max:300'],
        ];
    }
}
