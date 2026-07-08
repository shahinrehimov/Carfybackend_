<?php

namespace App\Http\Requests\App\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AppleNativeLoginRequest extends FormRequest
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
            // Apple identity token (JWT) obtained natively on the device.
            'identity_token' => ['required', 'string'],
            // Authorization code, exchanged server-side for a refresh token.
            'authorization_code' => ['nullable', 'string'],
            // Name/surname are only returned by Apple on the very first authorization.
            'name' => ['nullable', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'string', 'max:255'],
            'ref_code' => ['nullable', 'string'],
        ];
    }
}
