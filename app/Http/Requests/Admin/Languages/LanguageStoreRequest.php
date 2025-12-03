<?php

namespace App\Http\Requests\Admin\Languages;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="LanguageStoreRequest",
 *     type="object",
 *     title="Language Store Request",
 *     required={"key", "title"},
 *     @OA\Property(
 *         property="key",
 *         type="string",
 *         description="Key of the language",
 *         example="en",
 *         minLength=2,
 *         maxLength=2
 *     ),
 *     @OA\Property(
 *         property="title",
 *         type="string",
 *         description="Title of the language",
 *         example="English",
 *         maxLength=50
 *     )
 * )
 */
class LanguageStoreRequest extends FormRequest
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
            'key' => ['required', 'string', 'size:2'],
            'title' => ['required', 'string', 'max:50'],
        ];
    }
}
