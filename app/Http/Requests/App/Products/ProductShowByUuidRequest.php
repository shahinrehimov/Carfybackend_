<?php

namespace App\Http\Requests\App\Products;

use Illuminate\Foundation\Http\FormRequest;

class ProductShowByUuidRequest extends FormRequest
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
     * The CompanyProductVariant uuid is the route parameter, so neither the
     * variant slug nor the seller store_code are needed here.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'other_sellers' => ['nullable', 'string', 'in:yes,no'],
            'with_related' => ['nullable', 'string', 'in:yes,no'],
        ];
    }
}
