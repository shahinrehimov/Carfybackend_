<?php

namespace App\Http\Requests\Admin\Categories;

use App\Http\Requests\GeneralListRequest;
use App\Models\Category;
use Illuminate\Validation\Rule;

class CategoryLoadRequest extends GeneralListRequest
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
                'with_subs' => ['nullable', 'string', 'in:yes,no'],
                'parent_id' => ['nullable', 'integer', Rule::exists(Category::class, 'id')->whereNull('parent_id')->where('is_active', true)->whereNull('deleted_at')],
            ];
    }
}
