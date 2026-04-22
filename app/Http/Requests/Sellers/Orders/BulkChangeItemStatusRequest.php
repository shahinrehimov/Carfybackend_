<?php

namespace App\Http\Requests\Sellers\Orders;

use App\Enum\OrderItemStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkChangeItemStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'integer'],
            'status' => ['required', Rule::enum(OrderItemStatusEnum::class)],
            'cancel_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
