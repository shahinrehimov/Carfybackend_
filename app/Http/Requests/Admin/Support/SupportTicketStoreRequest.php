<?php

namespace App\Http\Requests\Admin\Support;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupportTicketStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', Rule::exists(Company::class, 'id')],
            'subject'    => ['required', 'string', 'max:500'],
            'body'       => ['required', 'string', 'max:5000'],
            'files'      => ['nullable', 'array', 'max:10'],
            'files.*'    => ['file', 'max:10240'],
        ];
    }
}
