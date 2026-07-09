<?php

namespace App\Http\Requests\Admin\Faqs;

use Illuminate\Foundation\Http\FormRequest;

class FaqChangeSortRequest extends FormRequest
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
