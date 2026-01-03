<?php

namespace App\Http\Requests\Admin\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Password;

class CompanyAdminUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyAdmin = $this->route('companyAdmin');

        return [
            'username' => ['sometimes', 'string', 'max:255', Rule::unique('company_admins', 'username')->ignore($companyAdmin->id)->whereNull('deleted_at')],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('company_admins', 'email')->ignore($companyAdmin->id)->whereNull('deleted_at')],
            'password' => ['nullable', Password::defaults()],
            'name' => ['sometimes', 'string', 'max:255'],
            'surname' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'password_must_change' => ['sometimes', 'boolean'],
            'image' => ['nullable', File::image()->max(2048)],
        ];
    }
}
