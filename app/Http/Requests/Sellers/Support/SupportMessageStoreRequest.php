<?php

namespace App\Http\Requests\Sellers\Support;

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
            'files.*' => ['file', 'extensions:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx', 'max:10240'],
        ];
    }
}
