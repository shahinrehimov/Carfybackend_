<?php

namespace App\Http\Requests\Admin\Support;

use Illuminate\Foundation\Http\FormRequest;

class SupportMessageStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body'    => ['required', 'string', 'max:5000'],
            'files'   => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:10240'],
        ];
    }
}
