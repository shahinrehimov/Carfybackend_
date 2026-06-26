<?php

namespace App\Http\Resources\Admin\Users;

use App\Services\LangService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @property mixed $id
 * @property mixed $email
 * @property mixed $full_name
 * @property mixed $gender
 * @property mixed $birth_date
 * @property mixed $email_verified_at
 * @property mixed $created_at
 * @property mixed $name
 * @property mixed $surname
 * @property mixed $image
 * @method isActive()
 * @method isEmailVerified()
 */
class UserResource extends JsonResource
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
            'image' => $this->image,
            'email' => $this->email,
            'name' => $this->name,
            'surname' => $this->surname,
            'full_name' => $this->full_name,
            'gender' => $this->gender,
            'gender_label' => LangService::instance()
                ->setDefault(Str::title($this->gender))
                ->getLang('gender_' . $this->gender),
            'birth_date' => $this->birth_date?->toDateString(),
            'is_active' => $this->isActive(),
            'email_is_verified' => $this->isEmailVerified(),
            'email_verified_at' => $this->email_verified_at?->toDateTimeString(),
            'created_date' => $this->created_at?->toDateTimeString(),
        ];
    }
}
