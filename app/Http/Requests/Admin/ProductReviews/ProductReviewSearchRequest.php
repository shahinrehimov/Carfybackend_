<?php

namespace App\Http\Requests\Admin\ProductReviews;

use App\Http\Requests\GeneralListRequest;

class ProductReviewSearchRequest extends GeneralListRequest
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
        return parent::rules() + [
                'approved' => ['nullable', 'string', 'in:yes,no'],
                'product_id' => ['nullable', 'integer'],
                'product_variant_id' => ['nullable', 'integer'],
                'user_id' => ['nullable', 'integer'],
                'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
                'product' => ['nullable', 'string', 'max:500'],
                'user' => ['nullable', 'string', 'max:500'],
                'review' => ['nullable', 'string', 'max:5000'],
            ];
    }
}
