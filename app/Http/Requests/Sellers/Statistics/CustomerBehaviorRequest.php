<?php

namespace App\Http\Requests\Sellers\Statistics;

use Illuminate\Foundation\Http\FormRequest;

class CustomerBehaviorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from'            => ['nullable', 'date'],
            'date_to'              => ['nullable', 'date', 'after_or_equal:date_from'],
            'abandoned_limit'      => ['nullable', 'integer', 'min:5', 'max:50'],
        ];
    }
}
