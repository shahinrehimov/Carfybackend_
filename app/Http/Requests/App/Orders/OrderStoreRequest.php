<?php

namespace App\Http\Requests\App\Orders;

use App\Models\Address;
use App\Rules\ValidPaymentMethod;
use App\Services\UserService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderStoreRequest extends FormRequest
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
        return [
            'payment_method' => [
                'required',
                'string',
                new ValidPaymentMethod(),
            ],
            'address_id' => [
                'required',
                'integer',
                Rule::exists(Address::class, 'id')
                    ->where('user_id', UserService::instance()->getUser()->id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
