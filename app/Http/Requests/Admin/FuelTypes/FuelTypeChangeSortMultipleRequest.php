<?php

namespace App\Http\Requests\Admin\FuelTypes;

use App\Models\FuelType;
use Illuminate\Foundation\Http\FormRequest;

class FuelTypeChangeSortMultipleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'        => ['required', 'array', 'size:' . FuelType::query()->count()],
            'items.*.id'   => ['required', 'integer', 'distinct', 'min:1'],
            'items.*.sort' => ['required', 'integer', 'min:0'],
        ];
    }
}
