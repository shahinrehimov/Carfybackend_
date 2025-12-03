<?php

namespace App\Http\Resources\Admin\Languages;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="LanguageResource",
 *     type="object",
 *     title="Language Resource",
 *     description="Language Resource",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="ID of the language"
 *     ),
 *     @OA\Property(
 *         property="title",
 *         type="string",
 *         description="Title of the language"
 *     ),
 *     @OA\Property(
 *         property="key",
 *         type="string",
 *         description="Key of the language"
 *     ),
 *     @OA\Property(
 *         property="is_active",
 *         type="boolean",
 *         description="Is Active"
 *     )
 * )
 *
 * @property mixed $id
 * @property mixed $title
 * @property mixed $key
 */
class LanguageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'key' => $this->key,
            'is_active' => $this->is_active ?? true,
        ];
    }
}
