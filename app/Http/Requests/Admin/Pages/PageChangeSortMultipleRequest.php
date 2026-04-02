<?php

namespace App\Http\Requests\Admin\Pages;

use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;

class PageChangeSortMultipleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'size:' . Page::query()->count()],
            'items.*.id' => ['required', 'integer', 'distinct', 'min:1'],
            'items.*.sort' => ['required', 'integer', 'min:0'],
        ];
    }
}
