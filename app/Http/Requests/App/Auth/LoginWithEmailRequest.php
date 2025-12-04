<?php

namespace App\Http\Requests\App\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginWithEmailRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required'],
        ];
    }
}
