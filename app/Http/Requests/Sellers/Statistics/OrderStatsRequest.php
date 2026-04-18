<?php

namespace App\Http\Requests\Sellers\Statistics;

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
            'period'    => ['filled', 'string', Rule::in(['daily', 'monthly'])],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
            'status'    => ['nullable', 'string', Rule::in(array_column(OrderStatusEnum::cases(), 'value'))],
        ];
    }
}
