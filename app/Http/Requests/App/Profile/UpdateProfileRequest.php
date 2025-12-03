<?php

namespace App\Http\Requests\App\Profile;

use App\Enum\GenderEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property mixed $city_id
 * @property mixed $area_id
 */
class UpdateProfileRequest extends FormRequest
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
            'full_name' => ['required', 'string', 'max:500'],
            //'email' => ['required', 'email', 'max:150'],
            'gender' => ['required', 'string', Rule::in(GenderEnum::cases())],
            'birth_date' => ['required', 'date', 'before:today'],
        ];
    }
}
