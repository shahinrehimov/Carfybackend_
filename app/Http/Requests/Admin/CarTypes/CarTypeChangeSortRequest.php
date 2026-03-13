<?php

namespace App\Http\Requests\Admin\CarTypes;

use Illuminate\Foundation\Http\FormRequest;

class CarTypeChangeSortRequest extends FormRequest
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
