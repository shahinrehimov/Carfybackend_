<?php

namespace App\Http\Requests\App\Products;

use Illuminate\Foundation\Http\FormRequest;

class ProductShowRequest extends FormRequest
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
            'variant' => ['nullable', 'string', 'max:10000'],
            'with_related' => ['nullable', 'string', 'in:yes,no'],
        ];
    }
}
