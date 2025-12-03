<?php

namespace App\Http\Requests\Admin\AddressTypes;

use App\Models\AddressType;
use Illuminate\Foundation\Http\FormRequest;

class AddressTypeChangeSortMultipleRequest extends FormRequest
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
            'items' => ['required', 'array', 'size:' . AddressType::query()->count()],
            'items.*.id' => ['required', 'integer', 'distinct', 'min:1'],
            'items.*.sort' => ['required', 'integer', 'min:0'],
        ];
    }
}
