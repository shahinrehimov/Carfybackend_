<?php

namespace App\Http\Requests\Admin\TransmissionTypes;

use App\Models\TransmissionType;
use Illuminate\Foundation\Http\FormRequest;

class TransmissionTypeChangeSortMultipleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'        => ['required', 'array', 'size:' . TransmissionType::query()->count()],
            'items.*.id'   => ['required', 'integer', 'distinct', 'min:1'],
            'items.*.sort' => ['required', 'integer', 'min:0'],
        ];
    }
}
