<?php

namespace App\Http\Resources\App\Auth;

use App\Services\LangService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @property mixed $token
 * @property mixed $gender
 * @property mixed $name
 * @property mixed $surname
 * @property mixed $email
 * @property mixed $birth_date
 * @property mixed $uuid
 * @property mixed $phone
 * @property mixed $email_verified_at
 * @property mixed $phone_verified_at
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
            'uuid' => $this->uuid,
            'email' => $this->email,
            'phone' => $this->phone,
            'name' => $this->name,
            'surname' => $this->surname,
            'gender' => $this->gender,
            'gender_label' => LangService::instance()
                ->setDefault(Str::title($this->gender))
                ->getLang('gender_' . $this->gender),
            'birth_date' => $this->birth_date?->toDateString(),
            'email_is_verified' => (bool) $this->email_verified_at,
            'phone_is_verified' => (bool) $this->phone_verified_at,
        ];
    }

    public function with($request): array
    {
        return [
            'token' => $this->token,
        ];
    }
}
