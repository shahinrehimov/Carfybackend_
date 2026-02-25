<?php

namespace App\Http\Requests\Admin\Company;

use App\Models\CompanyAdmin;
use App\Services\LangService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CompanyAdminUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $company = $this->route('company');

        if (!$company) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Company is required.')
                    ->getLang('company_admin_save_company_required')
            );
        }

        return [
            'username' => ['sometimes', 'string', 'max:255', Rule::unique(CompanyAdmin::class, 'username')->where('company_id', $company->id)->whereNull('deleted_at')->ignore($this->route('companyAdmin'))],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique(CompanyAdmin::class, 'email')->where('company_id', $company->id)->whereNull('deleted_at')->ignore($this->route('companyAdmin'))],
            'password' => ['nullable', Password::defaults()],
            'name' => ['sometimes', 'string', 'max:255'],
            'surname' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'password_must_change' => ['sometimes', 'boolean'],
            'image' => ['nullable', File::image()->max(2048)],
        ];
    }
}
