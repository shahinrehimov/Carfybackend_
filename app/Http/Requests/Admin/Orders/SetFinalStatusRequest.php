<?php

namespace App\Http\Requests\Admin\Orders;

use App\Enum\OrderStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetFinalStatusRequest extends FormRequest
{
    public function rules(): array
    {
        $allowedStatuses = [
            OrderStatusEnum::COURIER_ASSIGNED->value,
            OrderStatusEnum::CANCELED->value,
        ];

        return [
            'status' => ['required', 'string', Rule::in($allowedStatuses)],
            'cancel_reason' => [
                'required_if:status,' . OrderStatusEnum::CANCELED->value,
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
