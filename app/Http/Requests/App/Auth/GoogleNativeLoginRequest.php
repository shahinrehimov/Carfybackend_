<?php

namespace App\Http\Requests\App\Auth;

use Illuminate\Foundation\Http\FormRequest;

class GoogleNativeLoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
            // Google ID token (JWT) obtained natively on the device.
            'id_token' => ['required', 'string'],
            'device_type' => ['nullable', 'string', 'max:255'],
            'ref_code' => ['nullable', 'string'],
        ];
    }
}
