<?php

namespace App\Http\Requests\App\Addresses;

use App\Models\AddressType;
use App\Models\City;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddressStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
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
            'address_type_id' => ['required', 'integer', Rule::exists(AddressType::class, 'id')->where('is_active', true)->whereNull('deleted_at')],
            'city_id' => ['required', 'integer', Rule::exists(City::class, 'id')->where('is_active', true)->whereNull('deleted_at')],
            'address_line_1' => ['required', 'string', 'max:1000'],
            'address_line_2' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['required', 'string', 'max:100'],
            'longitude' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
        ];
    }
}
