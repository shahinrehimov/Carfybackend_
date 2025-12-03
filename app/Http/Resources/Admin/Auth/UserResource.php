<?php

namespace App\Http\Resources\Admin\Auth;

use App\Http\Resources\Admin\Roles\RolesListResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $username
 * @property mixed $email
 * @property mixed $name
 * @property mixed $surname
 * @property mixed $roles
 * @property mixed $token
 * @property mixed $image
 * @property mixed $phone
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roles = $this->roles;

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
            'role_ids' => data_get($roles, '*.id'),
            'roles' => RolesListResource::collection($roles),
        ];
    }

    public function with($request): array
    {
        return [
            'token' => $this->token,
        ];
    }
}
