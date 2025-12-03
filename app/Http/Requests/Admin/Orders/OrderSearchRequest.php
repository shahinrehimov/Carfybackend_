<?php

namespace App\Http\Requests\Admin\Orders;

use App\Enum\OrderPaymentStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Http\Requests\GeneralListRequest;
use App\Rules\ValidPaymentMethod;
use Illuminate\Validation\Rule;

class OrderSearchRequest extends GeneralListRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return parent::rules() + [
                'code' => ['nullable', 'string', 'max:15'],
                'status' => ['nullable', 'string', Rule::in(OrderStatusEnum::cases())],
                'payment_status' => ['nullable', 'string', Rule::in(OrderPaymentStatusEnum::cases())],
                'payment_method' => ['nullable', 'string', new ValidPaymentMethod()],
                'amount_min' => ['nullable', 'numeric', 'min:0'],
                'amount_max' => ['nullable', 'numeric', 'min:0'],
                'user' => ['nullable', 'string', 'max:500'],
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date'],
            ];
    }
}
