<?php

namespace App\Http\Requests\App\ProductReviews;

use Illuminate\Foundation\Http\FormRequest;

class ProductReviewStoreRequest extends FormRequest
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
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'name' => ['required', 'string', 'max:150'],
            'surname' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'review' => ['required', 'string', 'max:5000'],
        ];
    }
}
