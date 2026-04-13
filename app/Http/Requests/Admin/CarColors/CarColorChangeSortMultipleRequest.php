<?php

namespace App\Http\Requests\Admin\CarColors;

use App\Models\ProductOptionItem;
use Illuminate\Foundation\Http\FormRequest;

class CarColorChangeSortMultipleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'        => ['required', 'array', 'size:' . ProductOptionItem::query()->forOption('color')->count()],
            'items.*.id'   => ['required', 'integer', 'distinct', 'min:1'],
            'items.*.sort' => ['required', 'integer', 'min:0'],
        ];
    }
}
