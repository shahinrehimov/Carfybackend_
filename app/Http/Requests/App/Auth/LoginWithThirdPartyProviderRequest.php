<?php

namespace App\Http\Requests\App\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginWithThirdPartyProviderRequest extends FormRequest
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
            'device_type' => ['required', 'string', 'in:android,ios,web'],
            'provider' => ['required', 'string', 'in:google'],
            'token' => ['required', 'string', 'max:200'],
        ];
    }
}
