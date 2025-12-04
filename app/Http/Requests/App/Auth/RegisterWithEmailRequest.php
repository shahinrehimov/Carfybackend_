<?php

namespace App\Http\Requests\App\Auth;

use App\Enum\GenderEnum;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterWithEmailRequest extends FormRequest
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
            'phone' => ['required', 'string', 'size:12', 'regex:/^994\d{9}$/', Rule::unique(User::class, 'phone')->whereNull('deleted_at')],
            'email' => ['required', 'email', 'max:150'],
            'gender' => ['nullable', 'string', Rule::in(GenderEnum::cases())],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
            ],
        ];
    }
}
