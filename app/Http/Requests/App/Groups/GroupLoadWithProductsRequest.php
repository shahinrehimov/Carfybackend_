<?php

namespace App\Http\Requests\App\Groups;

use Illuminate\Foundation\Http\FormRequest;

class GroupLoadWithProductsRequest extends FormRequest
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
            'limit' => ['filled', 'integer', 'min:1', 'max:50'],
        ];
    }
}
