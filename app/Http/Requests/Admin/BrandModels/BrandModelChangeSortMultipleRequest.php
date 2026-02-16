<?php

namespace App\Http\Requests\Admin\BrandModels;

use App\Models\Brand;
use App\Models\BrandModel;
use Illuminate\Foundation\Http\FormRequest;

class BrandModelChangeSortMultipleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Brand $brand */
        $brand = $this->route('brand');

        return [
            'items' => ['required', 'array', 'size:' . BrandModel::query()->where('brand_id', $brand?->id)->count()],
            'items.*.id' => ['required', 'integer', 'distinct', 'min:1'],
            'items.*.sort' => ['required', 'integer', 'min:0'],
        ];
    }
}
