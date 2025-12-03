<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GeneralListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'sort' => ['filled', 'string'],
            'sort_type' => ['filled', 'string', 'in:asc,desc'],
            'limit' => ['filled', 'integer', 'min:5', 'max:100'],
            'search' => ['nullable', 'string', 'max:500'],
        ];
    }
}
