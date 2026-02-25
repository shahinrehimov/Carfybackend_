<?php

namespace App\Http\Requests\Admin\Company;

use App\Models\City;
use App\Rules\FirstTranslationRequired;
use App\Services\LangService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class CompanyStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'voen' => ['required', 'string', 'max:255', Rule::unique('companies', 'voen')->whereNull('deleted_at')],
            'phone' => ['required', 'string', 'max:255', Rule::unique('companies', 'phone')->whereNull('deleted_at')],
            'email' => ['required', 'email', 'max:255', Rule::unique('companies', 'email')->whereNull('deleted_at')],
            'city_id' => ['required', 'numeric', Rule::exists(City::class, 'id')->whereNull('deleted_at')->where('is_active', true)],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'website' => ['nullable', 'url', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],

            // Translatable fields
            'translations' => ['required', 'array', 'size:' . count(LangService::instance()->getLanguages())],
            'translations.*.language_id' => ['required', 'integer', 'distinct', Rule::in(data_get(LangService::instance()->getLanguages(), '*.id'))],
            'translations.*.title' => [new FirstTranslationRequired('title'), 'nullable', 'string', 'max:300'],
            'translations.*.info' => ['nullable', 'string'],
            'translations.*.address' => ['nullable', 'string', 'max:500'],

            // Images
            'logo' => ['nullable', File::image()->max(2048)],
            'banner' => ['nullable', File::image()->max(2048)],

            // Root admin fields
            'root_admin_username' => ['required', 'string', 'max:255'],
            'root_admin_email' => ['required', 'email', 'max:255', Rule::unique('companies', 'root_admin_email')->whereNull('deleted_at')],
            'root_admin_name' => ['required', 'string', 'max:255'],
            'root_admin_surname' => ['required', 'string', 'max:255'],
        ];
    }
}
