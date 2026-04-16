<?php

namespace App\Http\Requests\Admin\Advertisements;

use App\Enum\AdvertisementPlatformEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class AdvertisementUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'       => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'link'        => ['nullable', 'string', 'url', 'max:2048'],
            'start_date'  => ['nullable', 'date', 'date_format:Y-m-d'],
            'end_date'    => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'is_active'   => ['nullable', 'boolean'],
            'platform'    => ['nullable', 'string', 'in:' . implode(',', AdvertisementPlatformEnum::values())],
            'image'       => ['nullable', File::types(['jpg', 'jpeg', 'png', 'webp', 'gif'])->max(5 * 1024)],
        ];
    }
}
