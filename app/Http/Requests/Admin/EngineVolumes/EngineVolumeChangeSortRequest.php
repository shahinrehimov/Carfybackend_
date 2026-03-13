<?php

namespace App\Http\Requests\Admin\EngineVolumes;

use Illuminate\Foundation\Http\FormRequest;

class EngineVolumeChangeSortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sort' => ['required', 'integer', 'min:0'],
        ];
    }
}
