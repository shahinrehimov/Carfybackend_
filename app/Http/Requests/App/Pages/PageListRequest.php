<?php

namespace App\Http\Requests\App\Pages;

use Illuminate\Foundation\Http\FormRequest;

class PageListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'limit' => ['filled', 'integer', 'min:1', 'max:50'],
        ];
    }
}
