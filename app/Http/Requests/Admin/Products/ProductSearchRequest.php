<?php

namespace App\Http\Requests\Admin\Products;

use App\Http\Requests\GeneralListRequest;
use App\Models\Company;
use App\Models\Group;
use Illuminate\Validation\Rule;

class ProductSearchRequest extends GeneralListRequest
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
                'is_set' => ['nullable', 'string', 'in:yes,no'],
                //'set_id' => ['nullable', 'integer', Rule::exists(Product::class, 'id')->where('is_active', true)->where('is_set', true)->whereNull('deleted_at')],
                'group_id' => ['nullable', 'integer', Rule::exists(Group::class, 'id')->where('is_active', true)->whereNull('deleted_at')],
                'company_id' => ['nullable', 'integer', Rule::exists(Company::class, 'id')->whereNull('deleted_at')],
            ];
    }
}
