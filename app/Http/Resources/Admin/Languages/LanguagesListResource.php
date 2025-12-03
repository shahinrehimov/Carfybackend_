<?php

namespace App\Http\Resources\Admin\Languages;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="LanguagesListResource",
 *     type="object",
 *     title="Languages List Resource",
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
 *     )
 * )
 *
 * @property mixed $title
 * @property mixed $id
 * @property mixed $key
 */
class LanguagesListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'key' => $this->key,
        ];
    }
}
