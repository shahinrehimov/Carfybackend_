<?php

namespace App\Http\Requests\App\Baskets;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Addresses one basket line of a listing. Only needed for listings that ask
 * which car the part is for, where the listing can sit in the basket once per
 * chosen car; otherwise the listing's single line is meant.
 */
class BasketLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'brand_id' => ['nullable', 'integer'],
            'brand_model_id' => ['nullable', 'integer'],
        ];
    }
}
