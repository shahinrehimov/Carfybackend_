<?php

namespace App\Http\Requests\Sellers\Support;

use App\Enum\SupportTicketStatusEnum;
use App\Http\Requests\GeneralListRequest;
use Illuminate\Validation\Rule;

class SupportTicketSearchRequest extends GeneralListRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'status' => ['nullable', 'string', Rule::in(SupportTicketStatusEnum::cases())],
        ];
    }
}
