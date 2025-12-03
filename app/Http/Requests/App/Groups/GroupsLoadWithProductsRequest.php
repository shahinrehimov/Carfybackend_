<?php

namespace App\Http\Requests\App\Groups;

use Illuminate\Foundation\Http\FormRequest;

class GroupsLoadWithProductsRequest extends FormRequest
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
            'groups' => ['required', 'array', 'min:1'],
            'groups.*.name' => ['required', 'string', 'distinct', 'max:600'],
            'groups.*.limit' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }
}
