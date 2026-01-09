<?php

namespace App\Http\Requests\Sellers\ProductVariants;

use App\Http\Requests\GeneralListRequest;
use App\Models\Group;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\Rule;

class ProductVariantSearchRequest extends GeneralListRequest
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
                'product_id' => ['nullable', 'integer', Rule::exists(Product::class, 'id')->whereNull('deleted_at')],
                'is_set' => ['nullable', 'string', 'in:yes,no'],
                'set_id' => ['nullable', 'integer', Rule::exists(ProductVariant::class, 'id')->where('is_active', true)->where('is_set', true)->whereNull('deleted_at')],
                'group_id' => ['nullable', 'integer', Rule::exists(Group::class, 'id')->where('is_active', true)->whereNull('deleted_at')],
            ];
    }
}
