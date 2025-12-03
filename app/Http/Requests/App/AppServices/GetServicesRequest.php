<?php

namespace App\Http\Requests\App\AppServices;

use App\Services\AppServices;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetServicesRequest extends FormRequest
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
            'services' => ['required', 'array', 'min:1'],
            'services.*.key' => ['required', 'string', Rule::in(AppServices::instance()->getServicesList())],
            'services.*.limit' => ['filled', 'int', 'min:1', 'max:100'],
        ];
    }
}
