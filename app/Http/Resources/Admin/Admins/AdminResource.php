<?php

namespace App\Http\Resources\Admin\Admins;

use App\Http\Resources\Admin\Roles\RolesListResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $username
 * @property mixed $id
 * @property mixed $email
 * @property mixed $name
 * @property mixed $surname
 * @property mixed $roles
 * @property mixed $image
 * @property mixed $phone
 */
class AdminResource extends JsonResource
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
            'email' => $this->email,
            'name' => $this->name,
            'surname' => $this->surname,
            'phone' => $this->phone,
            'image' => $this->whenLoaded('media', function () {
                return $this->image;
            }),
            'roles' => data_get($this->roles, '*.id'),
            'roles_title' => RolesListResource::collection($this->whenLoaded('roles')),
        ];
    }
}
