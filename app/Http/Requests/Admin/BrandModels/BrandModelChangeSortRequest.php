<?php

namespace App\Http\Requests\Admin\BrandModels;

use Illuminate\Foundation\Http\FormRequest;

class BrandModelChangeSortRequest extends FormRequest
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
