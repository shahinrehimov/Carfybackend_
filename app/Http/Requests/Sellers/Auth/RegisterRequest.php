<?php

namespace App\Http\Requests\Sellers\Auth;

use App\Models\City;
use App\Rules\FirstTranslationRequired;
use App\Services\LangService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            // Company fields
            'company.voen' => ['required', 'string', 'max:255', Rule::unique('companies', 'voen')->whereNull('deleted_at')],
            'company.phone' => ['required', 'string', 'max:255', Rule::unique('companies', 'phone')->whereNull('deleted_at')],
            'company.email' => ['required', 'email', 'max:255', Rule::unique('companies', 'email')->whereNull('deleted_at')],
            'company.city_id' => ['required', 'numeric', Rule::exists(City::class, 'id')->whereNull('deleted_at')->where('is_active', true)],
            'company.latitude' => ['nullable', 'numeric'],
            'company.longitude' => ['nullable', 'numeric'],
            'company.website' => ['nullable', 'url', 'max:255'],

            // Translatable fields
            'company.translations' => ['required', 'array', 'size:' . count(LangService::instance()->getLanguages())],
            'company.translations.*.language_id' => ['required', 'integer', 'distinct', Rule::in(data_get(LangService::instance()->getLanguages(), '*.id'))],
            'company.translations.*.title' => [new FirstTranslationRequired('title'), 'nullable', 'string', 'max:300'],
            'company.translations.*.info' => ['nullable', 'string'],
            'company.translations.*.address' => ['nullable', 'string', 'max:500'],

            // Images
            'company.logo' => ['nullable', File::image()->max(2048)],
            'company.banner' => ['nullable', File::image()->max(2048)],

            // Company Admin fields
            'admin.username' => ['required', 'string', 'max:255'],
            'admin.email' => ['required', 'email', 'max:255', Rule::unique('companies', 'root_admin_email')->whereNull('deleted_at')],
            'admin.password' => ['required', 'confirmed', Password::defaults()],
            'admin.name' => ['required', 'string', 'max:255'],
            'admin.surname' => ['required', 'string', 'max:255'],

            'device_type' => ['required', 'string', 'max:255'],
        ];
    }
}
