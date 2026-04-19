<?php

namespace App\Http\Requests\Sellers\Support;

use Illuminate\Foundation\Http\FormRequest;

class SupportTicketStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:500'],
            'body'    => ['required', 'string', 'max:5000'],
            'files'   => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:10240'],
        ];
    }
}
