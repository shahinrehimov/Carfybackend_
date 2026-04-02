<?php

namespace App\Http\Requests\Admin\CarColors;

use App\Models\CarColor;
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
            'items'        => ['required', 'array', 'size:' . CarColor::query()->count()],
            'items.*.id'   => ['required', 'integer', 'distinct', 'min:1'],
            'items.*.sort' => ['required', 'integer', 'min:0'],
        ];
    }
}
