<?php

namespace App\Http\Requests\App\Auth;

use App\Enum\UserRegisterTypesEnum;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ForgotPasswordRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:150', Rule::exists(User::class, 'email')->where('is_active', true)->where('register_type', UserRegisterTypesEnum::EMAIL->value)->whereNull('deleted_at')],
        ];
    }
}
