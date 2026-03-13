<?php

namespace App\Http\Requests\Admin\TransmissionTypes;

use Illuminate\Foundation\Http\FormRequest;

class TransmissionTypeChangeSortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sort' => ['required', 'integer', 'min:0'],
        ];
    }
}
