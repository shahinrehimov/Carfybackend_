<?php

namespace App\Http\Requests\Admin\Pages;

use App\Services\LangService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class PageUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'main_image' => ['filled', File::image()->max(10 * 1024)],
            'icon' => ['filled', File::image()->max(5 * 1024)],
            'images' => ['filled', 'array', 'min:1'],
            'images.*' => ['required', File::image()->max(10 * 1024)],
            'sort' => ['filled', 'integer', 'min:0'],
            'slug' => ['nullable', 'string', 'max:10000'],
            'translations' => ['required', 'array', 'size:' . count(LangService::instance()->getLanguages())],
            'translations.*.language_id' => ['required', 'integer', 'distinct', Rule::in(data_get(LangService::instance()->getLanguages(), '*.id'))],
            'translations.*.title' => ['required', 'string', 'max:1000'],
            'translations.*.description' => ['nullable', 'string', 'max:5000'],
            'translations.*.body' => ['nullable', 'string', 'max:1000000'],
            'translations.*.meta_title' => ['nullable', 'string', 'max:255'],
            'translations.*.meta_description' => ['nullable', 'string', 'max:500'],
            'translations.*.meta_keywords' => ['nullable', 'string', 'max:500'],
        ];
    }
}
