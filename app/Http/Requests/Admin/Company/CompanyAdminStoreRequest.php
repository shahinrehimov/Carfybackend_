<?php

namespace App\Http\Requests\Admin\Company;

use App\Models\CompanyAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Password;

class CompanyAdminStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255', Rule::unique(CompanyAdmin::class, 'username')->whereNull('deleted_at')],
            'email' => ['required', 'email', 'max:255', Rule::unique(CompanyAdmin::class, 'email')->whereNull('deleted_at')],
            'password' => ['required', Password::defaults()],
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'is_root' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['nullable', File::image()->max(2048)],
        ];
    }
}
