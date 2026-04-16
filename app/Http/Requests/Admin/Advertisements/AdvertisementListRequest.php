<?php

namespace App\Http\Requests\Admin\Advertisements;

use App\Enum\AdvertisementPlatformEnum;
use Illuminate\Foundation\Http\FormRequest;

class AdvertisementListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'limit'               => ['nullable', 'integer', 'min:5', 'max:100'],
            'search'              => ['nullable', 'string', 'max:500'],
            'platform'            => ['nullable', 'string', 'in:' . implode(',', AdvertisementPlatformEnum::values())],
            'is_active'           => ['nullable', 'boolean'],
            'is_currently_active' => ['nullable', 'boolean'],
            'date_from'           => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to'             => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
