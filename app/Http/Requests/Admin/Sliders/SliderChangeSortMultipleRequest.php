<?php

namespace App\Http\Requests\Admin\Sliders;

use App\Models\Slider;
use Illuminate\Foundation\Http\FormRequest;

class SliderChangeSortMultipleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'size:' . Slider::query()->count()],
            'items.*.id' => ['required', 'integer', 'distinct', 'min:1'],
            'items.*.sort' => ['required', 'integer', 'min:0'],
        ];
    }
}
