<?php

namespace App\Http\Resources\Sellers\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $username
 * @property mixed $email
 * @property mixed $name
 * @property mixed $surname
 * @property mixed $is_root
 * @property mixed $is_active
 * @property mixed $password_must_change
 * @property mixed $image
 * @property mixed $company
 * @property mixed $token
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
        return [
            'username' => $this->username,
            'email' => $this->email,
            'name' => $this->name,
            'surname' => $this->surname,
            'is_root' => $this->is_root,
            'is_active' => $this->is_active,
            'password_must_change' => $this->password_must_change,
            'image' => $this->image,
            'company' => [
                'id' => $this->company?->id,
                'title' => $this->company?->getLang('title'),
                'email' => $this->company?->email,
                'phone' => $this->company?->phone,
                'voen' => $this->company?->voen,
                'verified_at' => $this->company?->verified_at,
                'is_active' => $this->company?->is_active,
                'logo' => $this->company?->logo,
            ],
            'token' => $this->when(isset($this->token), $this->token),
        ];
    }
}
