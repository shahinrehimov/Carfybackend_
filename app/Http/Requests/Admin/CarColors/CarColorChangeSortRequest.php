<?php

namespace App\Http\Requests\Admin\CarColors;

use Illuminate\Foundation\Http\FormRequest;

class CarColorChangeSortRequest extends FormRequest
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
