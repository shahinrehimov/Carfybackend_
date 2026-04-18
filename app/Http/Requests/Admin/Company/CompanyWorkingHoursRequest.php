<?php

namespace App\Http\Requests\Admin\Company;

use Illuminate\Foundation\Http\FormRequest;

class CompanyWorkingHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hours'               => ['required', 'array', 'size:7'],
            'hours.*.day_of_week' => ['required', 'integer', 'min:1', 'max:7', 'distinct'],
            'hours.*.is_closed'   => ['required', 'boolean'],
            'hours.*.open_time'   => ['nullable', 'required_if:hours.*.is_closed,false', 'date_format:H:i'],
            'hours.*.close_time'  => ['nullable', 'required_if:hours.*.is_closed,false', 'date_format:H:i', 'after:hours.*.open_time'],
        ];
    }
}
