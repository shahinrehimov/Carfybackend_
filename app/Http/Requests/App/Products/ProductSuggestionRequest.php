<?php

namespace App\Http\Requests\App\Products;

use Illuminate\Foundation\Http\FormRequest;

class ProductSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['required', 'string', 'min:2', 'max:100'],
            'limit' => ['filled', 'integer', 'min:1', 'max:10'],
            'categories_limit' => ['filled', 'integer', 'min:0', 'max:10'],
        ];
    }
}
