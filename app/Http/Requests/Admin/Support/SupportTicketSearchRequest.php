<?php

namespace App\Http\Requests\Admin\Support;

use App\Enum\SupportTicketStatusEnum;
use App\Http\Requests\GeneralListRequest;
use App\Models\Admin;
use App\Models\Company;
use Illuminate\Validation\Rule;

class SupportTicketSearchRequest extends GeneralListRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'status'               => ['nullable', 'string', Rule::in(SupportTicketStatusEnum::cases())],
            'company_id'           => ['nullable', 'integer', Rule::exists(Company::class, 'id')],
            'assigned_to_admin_id' => ['nullable', 'integer', Rule::exists(Admin::class, 'id')],
        ];
    }
}
