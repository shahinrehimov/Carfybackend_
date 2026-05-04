<?php

namespace App\Http\Requests\App\Orders;

use App\Models\Address;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutPreviewRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer', Rule::exists(Address::class, 'id')],
        ];
    }
}
