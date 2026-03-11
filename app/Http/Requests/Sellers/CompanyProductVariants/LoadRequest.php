<?php

namespace App\Http\Requests\Sellers\CompanyProductVariants;

use App\Enum\ProductVariantApprovalStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sort' => ['filled', 'string'],
            'sort_type' => ['filled', 'string', 'in:asc,desc'],
            'limit' => ['filled', 'integer', 'min:5', 'max:100'],
            'search' => ['nullable', 'string', 'max:500'],
            'product_id' => ['nullable', 'integer'],
            'company_id' => ['nullable', 'integer'],
            'approval_status' => ['nullable', 'string', Rule::in(ProductVariantApprovalStatusEnum::cases())],
        ];
    }
}
