<?php

namespace App\Http\Requests\Sellers\StoreProfile;

use Illuminate\Foundation\Http\FormRequest;

class SendNotificationPhoneOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'size:12', 'regex:/^994\d{9}$/'],
        ];
    }
}
