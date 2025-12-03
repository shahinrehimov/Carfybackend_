<?php

namespace App\Http\Requests\Admin\Socials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class SocialUpdateRequest extends FormRequest
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
            'image' => [
                'filled',
                File::types(['jpg', 'jpeg', 'png', 'gif', 'svg'])
                    ->max(10 * 1024),
            ],
            'title' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'url', 'max:300'],
        ];
    }
}
