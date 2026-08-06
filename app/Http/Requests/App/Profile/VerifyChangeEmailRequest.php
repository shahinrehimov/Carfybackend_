<?php

namespace App\Http\Requests\App\Profile;

use Illuminate\Foundation\Http\FormRequest;

class VerifyChangeEmailRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:150'],
            'otp' => ['required', 'integer', 'digits:4'],
        ];
    }
}
