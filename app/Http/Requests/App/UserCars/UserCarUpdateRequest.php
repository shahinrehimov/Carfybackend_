<?php

namespace App\Http\Requests\App\UserCars;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UserCarUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'brand_model_id'      => ['required', 'integer', 'exists:brand_models,id'],
            'car_type_id'         => ['required', 'integer', 'exists:car_types,id'],
            'year'                => ['required', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'engine_volume_id'    => ['nullable', 'integer', 'exists:engine_volumes,id'],
            'fuel_type_id'        => ['nullable', 'integer', 'exists:fuel_types,id'],
            'transmission_type_id' => ['nullable', 'integer', 'exists:transmission_types,id'],
            'car_color_id'         => ['nullable', 'integer', 'exists:car_colors,id'],
            'seat_count'           => ['nullable', 'integer', 'min:1', 'max:100'],
            'engine_number'        => ['nullable', 'string', 'max:100'],
            'vin_number'           => ['nullable', 'string', 'max:100'],
            'photo'               => ['nullable', File::image()->max(10 * 1024)],
        ];
    }
}
