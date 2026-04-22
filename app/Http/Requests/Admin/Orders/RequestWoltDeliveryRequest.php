<?php

namespace App\Http\Requests\Admin\Orders;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestWoltDeliveryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', Rule::exists(Company::class, 'id')->whereNull('deleted_at')],
        ];
    }
}
