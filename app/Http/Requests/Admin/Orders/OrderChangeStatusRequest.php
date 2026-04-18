<?php

namespace App\Http\Requests\Admin\Orders;

use App\Enum\OrderStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderChangeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'        => ['required', 'string', Rule::in(OrderStatusEnum::cases())],
            'cancel_reason' => ['required_if:status,' . OrderStatusEnum::CANCELED->value, 'nullable', 'string', 'max:1000'],
        ];
    }
}
