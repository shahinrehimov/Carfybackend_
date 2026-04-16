<?php

namespace App\Http\Requests\Admin\Advertisements;

use Illuminate\Foundation\Http\FormRequest;

class AdvertisementClickListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'limit'     => ['nullable', 'integer', 'min:5', 'max:100'],
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to'   => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'user_type' => ['nullable', 'string', 'in:authenticated,guest,anonymous'],
        ];
    }
}
