<?php

namespace App\Http\Requests\App\Referral;

use Illuminate\Foundation\Http\FormRequest;

class CreateWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'card_last4'       => ['required', 'string', 'digits:4'],
            'card_holder_name' => ['required', 'string', 'max:150'],
        ];
    }
}
