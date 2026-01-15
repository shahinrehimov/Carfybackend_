<?php

namespace App\Http\Requests\Sellers\ProductVariants;

use Illuminate\Foundation\Http\FormRequest;

class ProductVariantApprovalStatusChangeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approval_status_message' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
