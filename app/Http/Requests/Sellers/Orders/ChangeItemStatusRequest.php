<?php

namespace App\Http\Requests\Sellers\Orders;

use App\Enum\OrderItemStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeItemStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(OrderItemStatusEnum::class)],
            'cancel_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
