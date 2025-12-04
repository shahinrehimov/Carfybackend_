<?php

namespace App\Http\Requests\App\Auth;

use App\Enum\GenderEnum;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterWithPhoneRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'surname' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'size:12', 'regex:/^994\d{9}$/'],
            'phone_confirmation_token' => ['required', 'string', 'max:100'],
            'otp' => ['required', 'integer', 'digits:4'],
            'email' => ['required', 'email', 'max:150', Rule::unique(User::class, 'email')->whereNull('deleted_at')],
            'gender' => ['nullable', 'string', Rule::in(GenderEnum::cases())],
            'birth_date' => ['nullable', 'date', 'before:today'],
        ];
    }
}
