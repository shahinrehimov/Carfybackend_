<?php

namespace App\Http\Requests\Admin\Orders;

use Illuminate\Foundation\Http\FormRequest;

class AssignCourierRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'courier_info' => ['required', 'array'],
        ];
    }
}
