<?php

namespace App\Http\Resources\Admin\CompanyAdmins;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $username
 * @property mixed $name
 * @property mixed $surname
 * @property mixed $image
 */
class CompanyAdminsListResource extends JsonResource
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
            'username' => $this->username,
            'full_name' => $this->name . ' ' . $this->surname,
            'image' => $this->whenLoaded('media', function () {
                return $this->image;
            }),
        ];
    }
}
