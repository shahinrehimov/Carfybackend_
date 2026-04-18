<?php

namespace App\Http\Requests\Admin\Statistics;

use App\Enum\OrderStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period'     => ['nullable', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'date_from'  => ['nullable', 'date_format:Y-m-d'],
            'date_to'    => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'status'     => ['nullable', 'string', Rule::in(OrderStatusEnum::cases())],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ];
    }
}
