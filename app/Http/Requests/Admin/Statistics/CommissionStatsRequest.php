<?php

namespace App\Http\Requests\Admin\Statistics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommissionStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period'     => ['nullable', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'date_from'  => ['nullable', 'date_format:Y-m-d'],
            'date_to'    => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'limit'      => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
