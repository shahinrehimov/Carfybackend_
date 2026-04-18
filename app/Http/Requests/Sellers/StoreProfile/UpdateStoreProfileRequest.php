<?php

namespace App\Http\Requests\Sellers\StoreProfile;

use App\Models\City;
use App\Services\CompanyAdminService;
use App\Services\LangService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateStoreProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = CompanyAdminService::instance()->getCompany()->id;

        return [
            'translations'              => ['required', 'array', 'size:' . count(LangService::instance()->getLanguages())],
            'translations.*.language_id' => ['required', 'integer', 'distinct', Rule::in(data_get(LangService::instance()->getLanguages(), '*.id'))],
            'translations.*.title'      => ['nullable', 'string', 'max:500'],
            'translations.*.info'       => ['nullable', 'string', 'max:5000'],
            'translations.*.address'    => ['nullable', 'string', 'max:1000'],

            'phone'     => ['nullable', 'string', 'max:50', Rule::unique('companies', 'phone')->ignore($companyId)->whereNull('deleted_at')],
            'email'     => ['nullable', 'email', 'max:255', Rule::unique('companies', 'email')->ignore($companyId)->whereNull('deleted_at')],
            'website'   => ['nullable', 'url', 'max:500'],
            'city_id'   => ['nullable', 'integer', Rule::exists(City::class, 'id')->where('is_active', true)->whereNull('deleted_at')],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'logo'   => ['nullable', File::image()->max(2 * 1024)],
            'banner' => ['nullable', File::image()->max(4 * 1024)],
        ];
    }
}
