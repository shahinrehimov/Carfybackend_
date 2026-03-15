<?php

namespace App\Http\Requests\Admin\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'device_type' => ['nullable', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ];
    }
}
