<?php

namespace App\Http\Requests\Admin\FuelTypes;

use Illuminate\Foundation\Http\FormRequest;

class FuelTypeChangeSortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sort' => ['required', 'integer', 'min:0'],
        ];
    }
}
