<?php

namespace App\Http\Requests\Sellers\Profile;

use App\Services\CompanyAdminService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class ProfileUpdateRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = CompanyAdminService::instance()->getUser();

        return [
            'username' => ['sometimes', 'string', 'max:255', Rule::unique('company_admins', 'username')->ignore($user->id)->whereNull('deleted_at')],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('company_admins', 'email')->ignore($user->id)->whereNull('deleted_at')],
            'name' => ['sometimes', 'string', 'max:255'],
            'surname' => ['sometimes', 'string', 'max:255'],
            'image' => ['nullable', File::image()->max(2048)],
        ];
    }
}
