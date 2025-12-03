<?php

namespace App\Http\Requests\Admin\Translations;

use App\Enum\TranslationGroupEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class GetTranslationsRequest extends FormRequest
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
            'group' => ['nullable', 'string', new Enum(TranslationGroupEnum::class)],
            'keyword' => ['nullable', 'string', 'max:255'],
            'text' => ['nullable', 'string', 'max:500'],
        ];
    }
}
