<?php

namespace App\Http\Requests\Admin\Company;

use App\Models\CompanyAdmin;
use App\Services\LangService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CompanyAdminStoreRequest extends FormRequest
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
            'username' => ['required', 'string', 'max:255', Rule::unique(CompanyAdmin::class, 'username')->where('company_id', $company->id)->whereNull('deleted_at')],
            'email' => ['required', 'email', 'max:255', Rule::unique(CompanyAdmin::class, 'email')->where('company_id', $company->id)->whereNull('deleted_at')],
            'password' => ['required', Password::defaults()],
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'is_root' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['nullable', File::image()->max(2048)],
        ];
    }
}
