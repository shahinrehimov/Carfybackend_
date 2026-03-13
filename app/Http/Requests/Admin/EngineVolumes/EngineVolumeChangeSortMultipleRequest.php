<?php

namespace App\Http\Requests\Admin\EngineVolumes;

use App\Models\EngineVolume;
use Illuminate\Foundation\Http\FormRequest;

class EngineVolumeChangeSortMultipleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'        => ['required', 'array', 'size:' . EngineVolume::query()->count()],
            'items.*.id'   => ['required', 'integer', 'distinct', 'min:1'],
            'items.*.sort' => ['required', 'integer', 'min:0'],
        ];
    }
}
