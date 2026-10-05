<?php

namespace App\Http\Requests\App\Baskets;

use Illuminate\Foundation\Http\FormRequest;

class BasketAddRequest extends FormRequest
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
            'quantity' => ['required', 'integer', 'min:1'],
            'sets' => ['filled', 'array', 'min:1'],
            'sets.*.id' => ['required', 'integer', 'distinct'],
            'sets.*.quantity' => ['required', 'integer', 'min:1'],

            // The car the part is for, on listings that ask (requires_fitment_selection).
            // Checked against the listing's fitments in FitmentSelectionService.
            'brand_id' => ['nullable', 'integer'],
            'brand_model_id' => ['nullable', 'integer'],
        ];
    }
}
